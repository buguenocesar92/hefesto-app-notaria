<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una version de documento. Solo metadatos: el archivo se descarga por una
 * ruta aparte, que autoriza antes de tocar el disco.
 */
class DocumentoVersionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'documento_id' => $this->documento_id,
            'version' => $this->version,
            'vigente' => $this->estaVigente(),
            'vigente_hasta' => $this->vigente_hasta?->toIso8601String(),
            'fecha_documento' => $this->fecha_documento?->toDateString(),
            'nombre_archivo' => $this->nombre_archivo,
            'mime' => $this->mime,
            'tamano_bytes' => $this->tamano_bytes,
            'hash_sha256' => $this->hash_sha256,
            'subido_por' => $this->subido_por,
            'observacion' => $this->observacion,
            'descargar' => route('api.v1.documentos.descargar', [
                'documento' => $this->documento_id,
                'version' => $this->id,
            ]),
        ];
    }
}