<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo de operacion que atiende la notaria.
 *
 * El catalogo lo define la notaria, con el nombre que ella usa. Esta
 * aplicacion no trae un catalogo impuesto de operaciones notariales, porque
 * no conoce el marco aplicable.
 *
 * `dias_alerta_por_defecto` y `dias_critico_por_defecto` son umbrales
 * INTERNOS que se copian a las etapas nuevas de este tipo de operacion. Con
 * NULL, las etapas quedan sin umbral y el semaforo no marca nada.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class TipoOperacion extends Model
{
    use HasUuids;

    protected $table = 'tipos_operacion';

    protected $fillable = [
        'tenant_id',
        'codigo',
        'nombre',
        'descripcion',
        'color',
        'duracion_atencion_minutos',
        'dias_alerta_por_defecto',
        'dias_critico_por_defecto',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'duracion_atencion_minutos' => 'integer',
            'dias_alerta_por_defecto' => 'integer',
            'dias_critico_por_defecto' => 'integer',
        ];
    }

    public function etapasPlantilla(): HasMany
    {
        return $this->hasMany(EtapaTipoOperacion::class)->orderBy('orden');
    }

    public function expedientes(): HasMany
    {
        return $this->hasMany(Expediente::class);
    }

    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }
}