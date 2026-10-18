<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuracion de la notaria: los valores que la notaria decide.
 *
 * `dias_alerta_por_defecto` y `dias_critico_por_defecto` vienen en NULL a
 * proposito. Sin umbral configurado, el semaforo NO marca nada: no inventa.
 *
 * `parametros_por_confirmar` arranca con las CLAVES de los datos que un
 * notario debe confirmar y SIN VALORES. El valor y el fundamento los carga la
 * persona de la notaria. Sembrar un valor de ejemplo seria inventar un plazo.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class ConfiguracionNotaria extends Model
{
    use HasUuids;

    protected $table = 'configuracion_notaria';

    protected $fillable = [
        'tenant_id',
        'zona_horaria',
        'dias_alerta_por_defecto',
        'dias_critico_por_defecto',
        'aviso_umbral_no_configurado',
        'parametros_por_confirmar',
    ];

    protected function casts(): array
    {
        return [
            'parametros_por_confirmar' => 'array',
            'dias_alerta_por_defecto' => 'integer',
            'dias_critico_por_defecto' => 'integer',
        ];
    }

    public function notaria(): BelongsTo
    {
        return $this->belongsTo(Notaria::class, 'tenant_id');
    }

    /** True si la notaria todavia no configuro el umbral del semaforo. */
    public function sinUmbral(): bool
    {
        return $this->dias_alerta_por_defecto === null;
    }
}