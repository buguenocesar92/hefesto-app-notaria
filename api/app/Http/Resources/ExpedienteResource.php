<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un expediente con su peor nivel de semaforo.
 *
 * `nivel` es el PEOR nivel entre las etapas. Sirve para ordenar el listado de
 * expedientes por urgencia sin abrir cada uno, que es la pregunta que la
 * notaria se hace al entrar a la pantalla.
 */
class ExpedienteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $nivel = $this->peorNivel();

        return [
            'id' => $this->id,
            'folio' => $this->folio,
            'numero_protocolo' => $this->numero_protocolo,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado->value,
            'estado_etiqueta' => $this->estado->etiqueta(),
            'fecha_inicio' => $this->fecha_inicio?->toDateString(),
            'fecha_cierre' => $this->fecha_cierre?->toDateString(),
            'profesional_responsable_id' => $this->profesional_responsable_id,

            'tipo_operacion' => $this->whenLoaded('tipoOperacion', fn (): array => [
                'id' => $this->tipoOperacion->id,
                'codigo' => $this->tipoOperacion->codigo,
                'nombre' => $this->tipoOperacion->nombre,
                'color' => $this->tipoOperacion->color,
            ]),

            'nivel' => $nivel->value,
            'nivel_etiqueta' => $nivel->etiqueta(),
            'atrasado' => $nivel->estaAtrasada(),

            'etapas' => EtapaResource::collection($this->whenLoaded('etapas')),
            'antecedentes_pendientes' => $this->whenLoaded(
                'antecedentes',
                fn (): int => $this->antecedentes
                    ->where('estado', \App\Enums\EstadoAntecedente::Pendiente)
                    ->count()
            ),
            'documentos_vigentes' => $this->whenLoaded(
                'documentos',
                fn (): int => $this->documentos
                    ->filter(fn ($documento): bool => $documento->estado === \App\Enums\EstadoDocumento::Vigente)
                    ->count()
            ),
        ];
    }
}