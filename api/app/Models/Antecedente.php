<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Antecedente: algo que hay que conseguir para poder avanzar.
 *
 * Es la tabla que ataca el problema real del negocio: un expediente parado
 * porque nadie fue a buscar lo que faltaba. Al registrar el antecedente por
 * separado, se sabe a quien se le pidio, cuando, y si todavia no llega.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Antecedente extends Model
{
    use HasUuids;

    protected $table = 'antecedentes';

    protected $fillable = [
        'tenant_id',
        'expediente_id',
        'etapa_id',
        'descripcion',
        'solicitado_a',
        'estado',
        'responsable_id',
        'solicitado_en',
        'recibido_en',
        'respuesta',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoAntecedente::class,
            'solicitado_en' => 'datetime',
            'recibido_en' => 'datetime',
        ];
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function etapa(): BelongsTo
    {
        return $this->belongsTo(Etapa::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }

    /** Los que todavia no llegaron: los que tienen el expediente parado. */
    public function scopeEsperando(Builder $consulta): Builder
    {
        return $consulta->where('estado', EstadoAntecedente::Pendiente->value);
    }
}