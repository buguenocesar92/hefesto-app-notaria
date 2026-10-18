<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alerta del semaforo: el registro de que el sistema se dio cuenta solo.
 *
 * Una alerta ABIERTA por etapa y nivel. El indice unico parcial
 * `alertas_semaforo_abierta_por_etapa_nivel` lo impone en la base: correr el
 * semaforo diez veces no genera diez alertas, actualiza la misma.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class AlertaSemaforo extends Model
{
    use HasUuids;

    protected $table = 'alertas_semaforo';

    protected $fillable = [
        'tenant_id',
        'expediente_id',
        'etapa_id',
        'nivel',
        'dias_en_estado',
        'umbral_dias',
        'umbral_cruzado_en',
        'ultima_revision_en',
        'veces_revisada',
        'cerrada_por',
        'cerrada_en',
        'motivo_cierre',
    ];

    protected function casts(): array
    {
        return [
            'nivel' => NivelSemaforo::class,
            'dias_en_estado' => 'integer',
            'umbral_dias' => 'integer',
            'veces_revisada' => 'integer',
            'umbral_cruzado_en' => 'datetime',
            'ultima_revision_en' => 'datetime',
            'cerrada_en' => 'datetime',
        ];
    }

    public function etapa(): BelongsTo
    {
        return $this->belongsTo(Etapa::class);
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function estaAbierta(): bool
    {
        return $this->cerrada_en === null;
    }

    public function scopeAbiertas(Builder $consulta): Builder
    {
        return $consulta->whereNull('cerrada_en');
    }
}