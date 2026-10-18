<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\NivelSemaforo;
use App\Models\Etapa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una etapa con su semaforo ya calculado.
 *
 * El semaforo viaja como DATOS ya resueltos (`nivel`, `dias_en_estado`,
 * `dias_alerta`) y no como una regla que el navegador tenga que aplicar. La
 * interfaz pinta lo que le dicen, y no decide por su cuenta cuando algo esta
 * atrasado.
 *
 * Cuando el nivel es `sin_umbral`, `dias_alerta` viaja en null y `aviso`
 * explica por que no se marco nada. La interfaz tiene que mostrarlo, no
 * esconderlo: si la notaria ve un tablero con etapas sin color y sin
 * explicacion, va a pensar que el sistema esta roto.
 */
class EtapaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $semaforo = $this->semaforo();

        return [
            'id' => $this->id,
            'expediente_id' => $this->expediente_id,
            'orden' => $this->orden,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado->value,
            'estado_etiqueta' => $this->estado->etiqueta(),
            'requiere_antecedente' => $this->requiere_antecedente,
            'iniciada_en' => $this->iniciada_en?->toIso8601String(),
            'terminada_en' => $this->terminada_en?->toIso8601String(),
            'observacion' => $this->observacion,
            'profesional_responsable_id' => $this->profesional_responsable_id,

            // --- El semaforo ---
            'nivel' => $semaforo->nivel->value,
            'nivel_etiqueta' => $semaforo->nivel->etiqueta(),
            'atrasada' => $semaforo->estaAtrasada(),
            'dias_en_estado' => $semaforo->diasEnEstado,
            'dias_alerta' => $semaforo->diasAlerta,
            'dias_critico' => $semaforo->diasCritico,
            'semaforo_calculado_en' => $this->semaforo_calculado_en?->toIso8601String(),
            'aviso' => $this->aviso($semaforo->nivel),
            'antecedentes_pendientes' => $this->antecedentesPendientes(),
        ];
    }

    private function aviso(NivelSemaforo $nivel): ?string
    {
        if ($nivel !== NivelSemaforo::SinUmbral) {
            return null;
        }

        return 'Esta etapa no tiene umbral configurado. El semaforo no la marca porque '
            . 'la notaria todavia no definiio cuantos dias espera antes de alarmarse. '
            . 'No hay ningun plazo legal aqui: es una alarma interna.';
    }

    private function antecedentesPendientes(): int
    {
        // Conteo perezoso: solo se consulta si la etapa pide antecedentes.
        if (! $this->requiere_antecedente) {
            return 0;
        }

        return $this->antecedentes
            ->where('estado', \App\Enums\EstadoAntecedente::Pendiente)
            ->count();
    }
}