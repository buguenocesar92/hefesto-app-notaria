<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cita o bloqueo de agenda.
 *
 * `estado = bloqueo` es tiempo que la notaria se guarda para si. Los demas
 * estados son atenciones a una persona.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class AgendaCita extends Model
{
    use HasUuids;

    protected $table = 'agenda_citas';

    protected $fillable = [
        'tenant_id',
        'profesional_id',
        'expediente_id',
        'tipo_operacion_id',
        'titulo',
        'estado',
        'inicio',
        'fin',
        'duracion_minutos',
        'bloqueo',
        'notas',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoCita::class,
            'inicio' => 'datetime',
            'fin' => 'datetime',
            'duracion_minutos' => 'integer',
        ];
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class);
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function tipoOperacion(): BelongsTo
    {
        return $this->belongsTo(TipoOperacion::class);
    }

    public function esBloqueo(): bool
    {
        return $this->estado === EstadoCita::Bloqueo;
    }
}