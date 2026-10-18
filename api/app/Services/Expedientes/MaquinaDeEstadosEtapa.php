<?php

declare(strict_types=1);

namespace App\Services\Expedientes;

use App\Enums\EstadoEtapa;
use App\Enums\EstadoExpediente;
use App\Models\Etapa;
use App\Models\Expediente;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de transicion de una etapa.
 *
 * LO QUE ESTA CLASE NO HACE
 * -------------------------
 * No impone un procedimiento notarial. No sabe que una firma tiene que venir
 * antes que otra cosa, ni que una operacion exige tales documentos, porque
 * ese marco no lo conoce esta aplicacion y no le corresponde.
 *
 * Lo que si hace es lo de todo software de tramite: que los estados sean
 * coherentes entre si y que cada cambio quede con fecha. Si las etapas no
 * guardan cuando empezaron y cuando terminaron, el semaforo no tiene nada que
 * medir y el expediente no tiene historia.
 *
 * El unico estado que esta clase se Niega a dejar pasar es
 * `terminada` sin fecha de termino: una etapa terminada sin fecha es una
 * etapa que el tablero contaria como sigue esperando para siempre.
 */
class MaquinaDeEstadosEtapa
{
    /**
     * Aplica un cambio de estado a una etapa y deja las fechas coherentes.
     */
    public function cambiar(Etapa $etapa, EstadoEtapa $nuevo, ?string $comentario = null): Etapa
    {
        return DB::transaction(function () use ($etapa, $nuevo, $comentario): Etapa {
            $anterior = $etapa->estado;

            $etapa->estado = $nuevo;

            // Las fechas tienen que contar la historia solos. Si alguien
            // termina una etapa y no se pone la fecha, el semaforo la sigue
            // contando como espera.
            if ($nuevo->espera()) {
                // Volvio a un estado que espera: si venia de terminada, la
                // fecha de termino deja de tener sentido.
                $etapa->terminada_en = null;

                if ($etapa->iniciada_en === null) {
                    $etapa->iniciada_en = now();
                }
            }

            if ($nuevo === EstadoEtapa::Terminada || $nuevo === EstadoEtapa::Omitida) {
                $etapa->terminada_en = now();

                if ($etapa->iniciada_en === null) {
                    // Nunca empezo pero ya termino: se deja constancia de
                    // cuando se cerro, que es un dato real.
                    $etapa->iniciada_en = $etapa->terminada_en;
                }
            }

            $etapa->save();

            if ($comentario !== null && $comentario !== '') {
                $this->registrarComentario($etapa, $anterior, $nuevo, $comentario);
            }

            return $etapa->refresh();
        });
    }

    /**
     * El estado global del expediente se DERIVA de sus etapas.
     *
     * Que alguien pueda marcar "cerrado" con etapas abiertas por el camino es
     * justamente el tipo de dato que despues nadie sabe defender ante una
     * auditoria. Por eso el estado global no se elige: sale de aca.
     */
    public function recalcularEstadoExpediente(Expediente $expediente): Expediente
    {
        $expediente->loadMissing('etapas');

        $tienePendientes = $expediente->etapas
            ->contains(static fn (Etapa $etapa): bool => $etapa->estado->espera());

        if ($expediente->estado === EstadoExpediente::Anulado) {
            // Un expediente anulado no se revive solo.
            return $expediente;
        }

        if ($tienePendientes) {
            $expediente->estado = EstadoExpediente::EnTramite;
            $expediente->fecha_cierre = null;
        } else {
            $expediente->estado = EstadoExpediente::Cerrado;
            $expediente->fecha_cierre = $expediente->fecha_cierre ?? now()->toDateString();
        }

        $expediente->save();

        return $expediente;
    }

    private function registrarComentario(
        Etapa $etapa,
        EstadoEtapa $anterior,
        EstadoEtapa $nuevo,
        string $comentario,
    ): void {
        // El comentario vive en la observacion de la etapa. No hay tabla de
        // eventos aparte: el historial completo de un expediente se lee
        // ordenando sus etapas por `orden`.
        $previa = $etapa->observacion;

        $etapa->observacion = trim(sprintf(
            "[%s] %s -> %s: %s%s",
            now()->toDateTimeString(),
            $anterior->etiqueta(),
            $nuevo->etiqueta(),
            $comentario,
            $previa !== null ? "\n" . $previa : ''
        ));

        $etapa->save();
    }
}