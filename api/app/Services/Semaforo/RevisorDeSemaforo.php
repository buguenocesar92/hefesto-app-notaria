<?php

declare(strict_types=1);

namespace App\Services\Semaforo;

use App\Enums\NivelSemaforo;
use App\Models\AlertaSemaforo;
use App\Models\Etapa;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Corre el semaforo y deja constancia de lo que encontro.
 *
 * QUE HACE
 * --------
 * Recorre las etapas que pueden estar esperando, calcula su nivel con
 * `SemaforoEtapa`, escribe `dias_en_estado` y `nivel_semaforo` en la etapa, y
 * abre o actualiza una alerta en `alertas_semaforo` cuando la etapa esta
 * atrasada.
 *
 * QUE NO HACE, Y ES LO IMPORTANTE
 * -------------------------------
 * No inventa umbrales. Si la etapa no tiene `dias_alerta`, el nivel queda en
 * `sin_umbral` y no se abre ninguna alerta. Preferimos un tablero con huecos
 * a un tablero con numeros falsos: una notaria que ve "rojo" donde no hay
 * plazo pierde la confianza en el tablero entero, y con ella, la utilidad.
 *
 * UNA ALERTA ABIERTA POR ETAPA Y NIVEL
 * ------------------------------------
 * La alerta se abre la primera vez que la etapa cruza el umbral. Mientras
 * siga en ese nivel, la alerta se ACTUALIZA (suben los dias). Si la etapa
 * vuelve a verde, la alerta se CIERRA; si vuelve a atrasarse, se abre una
 * nueva, que es un hecho distinto.
 *
 * Por que importa: una etapa que lleva veinte dias en rojo es un problema,
 * y una que lleva veinte dias en rojo APARECIO en rojo ayer es otro
 * problema. El tablero tiene que poder distinguir las dos cosas, y por eso
 * existe el registro historico.
 *
 * COMO SE EJECUTA
 * ---------------
 *   php artisan notaria:revisar-semaforo
 *
 * Se puede correr a mano, y tambien se agenda (ver `routes/console.php`).
 */
class RevisorDeSemaforo
{
    /**
     * Recorre las etapas esperando de la notaria en curso y actualiza el
     * semaforo.
     *
     * @param  bool  $guardar  si es false, calcula pero no escribe (util para
     *                          previsualizar el tablero)
     * @return Collection<int, Etapa> las etapas que quedaron atrasadas
     */
    public function revisar(bool $guardar = true): Collection
    {
        $ahora = CarbonImmutable::now();
        $atrasadas = collect();

        Etapa::query()
            ->esperando()
            ->with('expediente')
            ->chunkById(200, function (Collection $etapas) use ($ahora, $guardar, $atrasadas): void {
                foreach ($etapas as $etapa) {
                    $semaforo = $etapa->semaforo();

                    if ($guardar) {
                        $this->registrar($etapa, $semaforo, $ahora);
                    }

                    if ($semaforo->estaAtrasada()) {
                        $atrasadas->push($etapa);
                    }
                }
            });

        return $atrasadas;
    }

    /**
     * Escribe el nivel en la etapa y abre, actualiza o cierra la alerta.
     */
    private function registrar(Etapa $etapa, SemaforoEtapa $semaforo, CarbonImmutable $ahora): void
    {
        // 1. El nivel derivado SIEMPRE se escribe, incluso sin umbral: asi el
        //    tablero puede mostrar "sin umbral configurado" en vez de dejar
        //    la etapa con un valor viejo que ya no es cierto.
        $cambiaNivel = $etapa->nivel_semaforo !== $semaforo->nivel
            || $etapa->dias_en_estado !== $semaforo->diasEnEstado
            || $etapa->semaforo_calculado_en === null;

        if ($cambiaNivel) {
            $etapa->forceFill([
                'nivel_semaforo' => $semaforo->nivel,
                'dias_en_estado' => $semaforo->diasEnEstado,
                'semaforo_calculado_en' => $ahora,
            ])->save();
        }

        // 2. La alerta solo existe si hay atraso.
        if ($semaforo->nivel === NivelSemaforo::Rojo || $semaforo->nivel === NivelSemaforo::Amarillo) {
            $this->abrirOActualizarAlerta($etapa, $semaforo, $ahora);
        } else {
            $this->cerrarAlertasVigentes($etapa, $ahora);
        }
    }

    private function abrirOActualizarAlerta(Etapa $etapa, SemaforoEtapa $semaforo, CarbonImmutable $ahora): void
    {
        $existente = AlertaSemaforo::query()
            ->abiertas()
            ->where('etapa_id', $etapa->id)
            ->where('nivel', $semaforo->nivel->value)
            ->first();

        if ($existente !== null) {
            // La etapa sigue en el mismo nivel: no es un hecho nuevo, es el
            // mismo hecho un dia mas tarde.
            $existente->forceFill([
                'dias_en_estado' => $semaforo->diasEnEstado,
                'ultima_revision_en' => $ahora,
                'veces_revisada' => $existente->veces_revisada + 1,
            ])->save();

            return;
        }

        // Si venia de un nivel inferior y ahora sube a este, la alerta del
        // nivel anterior se cierra y esta se abre nueva.
        $this->cerrarAlertasDeOtrosNiveles($etapa, $semaforo->nivel, $ahora);

        try {
            AlertaSemaforo::create([
                'tenant_id' => $etapa->tenant_id,
                'expediente_id' => $etapa->expediente_id,
                'etapa_id' => $etapa->id,
                'nivel' => $semaforo->nivel->value,
                'dias_en_estado' => $semaforo->diasEnEstado,
                'umbral_dias' => $semaforo->nivel === NivelSemaforo::Rojo
                    ? $semaforo->diasCritico
                    : $semaforo->diasAlerta,
                'umbral_cruzado_en' => $ahora,
                'ultima_revision_en' => $ahora,
                'veces_revisada' => 1,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Gano otra corrida con la misma etapa y nivel. No es un error:
            // el indice unico hizo lo que tiene que hacer.
        }
    }

    /** Cierra las alertas de otros niveles de la misma etapa. */
    private function cerrarAlertasDeOtrosNiveles(
        Etapa $etapa,
        NivelSemaforo $nivelActual,
        CarbonImmutable $ahora,
    ): void {
        AlertaSemaforo::query()
            ->abiertas()
            ->where('etapa_id', $etapa->id)
            ->where('nivel', '!=', $nivelActual->value)
            ->update([
                'cerrada_en' => $ahora,
                'motivo_cierre' => 'El nivel del semaforo cambio.',
                'updated_at' => $ahora,
            ]);
    }

    /** Cierra cualquier alerta abierta de la etapa que ya no esta atrasada. */
    private function cerrarAlertasVigentes(Etapa $etapa, CarbonImmutable $ahora): void
    {
        AlertaSemaforo::query()
            ->abiertas()
            ->where('etapa_id', $etapa->id)
            ->update([
                'cerrada_en' => $ahora,
                'motivo_cierre' => 'La etapa dejo de estar atrasada.',
                'updated_at' => $ahora,
            ]);
    }
}