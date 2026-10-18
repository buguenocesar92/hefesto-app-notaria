<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Profesional que recibe agenda. Tabla aparte de `usuarios` porque no todas
 * las personas que atienden tienen cuenta, ni todas las cuentas atienden.
 *
 * `cargo` NO lo interpreta esta aplicacion: no sabe que puede hacer un
 * notario segun la ley aplicable. El permiso real esta en `usuarios.rol`.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Profesional extends Model
{
    use HasUuids;

    protected $table = 'profesionales';

    protected $fillable = [
        'tenant_id',
        'usuario_id',
        'nombre',
        'apellidos',
        'cargo',
        'correo',
        'telefono',
        'duracion_atencion_minutos',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'duracion_atencion_minutos' => 'integer',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(AgendaCita::class);
    }

    public function expedientes(): HasMany
    {
        return $this->hasMany(Expediente::class, 'profesional_responsable_id');
    }

    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre . ' ' . ($this->apellidos ?? ''));
    }
}