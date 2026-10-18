<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Etapa en la PLANTILLA de un tipo de operacion.
 *
 * Cuando se abre un expediente se copian a `etapas`. Por eso son dos tablas:
 * la plantilla se puede corregir sin tocar los expedientes ya abiertos, y
 * cada expediente conserva las etapas con las que se abrio.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class EtapaTipoOperacion extends Model
{
    use HasUuids;

    protected $table = 'etapas_tipo_operacion';

    protected $fillable = [
        'tenant_id',
        'tipo_operacion_id',
        'orden',
        'nombre',
        'descripcion',
        'requiere_antecedente',
        'dias_alerta',
        'dias_critico',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'requiere_antecedente' => 'boolean',
            'dias_alerta' => 'integer',
            'dias_critico' => 'integer',
        ];
    }

    public function tipoOperacion(): BelongsTo
    {
        return $this->belongsTo(TipoOperacion::class);
    }
}