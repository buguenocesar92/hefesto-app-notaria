<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Documento del expediente: la FICHA LOGICA.
 *
 * Un documento NUNCA se borra. Cuando se sube una version nueva, este
 * registro pasa a `reemplazado` y se crea la version nueva. El historial
 * queda entero.
 *
 * Los archivos concretos estan en `documento_versiones`.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Documento extends Model
{
    use HasUuids;

    protected $table = 'documentos';

    protected $fillable = [
        'tenant_id',
        'expediente_id',
        'nombre',
        'tipo',
        'estado',
        'version_actual',
        'fecha_documento',
        'observacion',
        'creado_por',
        'reemplazado_por',
        'reemplazado_en',
        'reemplazado_por_documento_id',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoDocumento::class,
            'version_actual' => 'integer',
            'fecha_documento' => 'datetime',
            'reemplazado_en' => 'datetime',
        ];
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(DocumentoVersion::class)->orderBy('version');
    }

    /** La version que aplica hoy, o null si se marco como reemplazado. */
    public function versionVigente(): ?DocumentoVersion
    {
        return $this->versiones()->whereNull('vigente_hasta')->first();
    }

    public function estaVigente(): bool
    {
        return $this->estado === EstadoDocumento::Vigente;
    }
}