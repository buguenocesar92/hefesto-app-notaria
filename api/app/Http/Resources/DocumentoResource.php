<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un documento, con TODO su historial de versiones.
 *
 * Que las versiones viajen siempre junto al documento no es un capricho de la
 * interfaz: es la regla del sistema. Si se pudiera pedir "solo el vigente" y
 * fuera facil que la pantalla lo hiciera, tarde o temprano alguien pondria un
 * boton de borrar al lado.
 */
class DocumentoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expediente_id' => $this->expediente_id,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo,
            'estado' => $this->estado->value,
            'estado_etiqueta' => $this->estado->etiqueta(),
            'version_actual' => $this->version_actual,
            'fecha_documento' => $this->fecha_documento?->toDateString(),
            'observacion' => $this->observacion,
            'creado_por' => $this->creado_por,
            'reemplazado_por' => $this->reemplazado_por,
            'reemplazado_en' => $this->reemplazado_en?->toIso8601String(),

            // El historial COMPLETO, en orden. Nunca se recorta.
            'versiones' => DocumentoVersionResource::collection($this->whenLoaded('versiones')),
        ];
    }
}