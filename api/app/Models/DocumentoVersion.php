<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El archivo de una version de documento.
 *
 * Una fila por version. Nunca se edita una fila existente: si el archivo
 * estaba mal, se sube otra version y esta queda con `vigente_hasta` distinto
 * de NULL, que es como se sabe que dejo de ser la buena.
 *
 * `ruta_archivo` es RELATIVA al volumen de documentos. El nombre que se
 * descarga es `nombre_archivo`.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class DocumentoVersion extends Model
{
    use HasUuids;

    protected $table = 'documento_versiones';

    protected $fillable = [
        'tenant_id',
        'documento_id',
        'version',
        'fecha_documento',
        'ruta_archivo',
        'nombre_archivo',
        'mime',
        'tamano_bytes',
        'hash_sha256',
        'subido_por',
        'vigente_hasta',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'tamano_bytes' => 'integer',
            'fecha_documento' => 'datetime',
            'vigente_hasta' => 'datetime',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    public function estaVigente(): bool
    {
        return $this->vigente_hasta === null;
    }
}