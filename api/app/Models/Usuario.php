<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RolUsuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Persona con cuenta en la notaria.
 *
 * La identidad vive en Authentik: aqui solo se guarda `authentik_sub`. No
 * hay contrasena en esta aplicacion.
 *
 * `rol` es el permiso DENTRO de la notaria. Ver `App\Enums\RolUsuario`.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Usuario extends Model
{
    use HasUuids;

    protected $table = 'usuarios';

    protected $fillable = [
        'tenant_id',
        'authentik_sub',
        'nombre',
        'apellidos',
        'correo',
        'telefono',
        'rol',
        'activo',
        'ultimo_acceso',
    ];

    protected function casts(): array
    {
        return [
            'rol' => RolUsuario::class,
            'activo' => 'boolean',
            'ultimo_acceso' => 'datetime',
        ];
    }

    public function notaria(): BelongsTo
    {
        return $this->belongsTo(Notaria::class, 'tenant_id');
    }

    public function profesional(): HasOne
    {
        return $this->hasOne(Profesional::class);
    }

    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }

    public function iniciales(): string
    {
        $letraNombre = mb_substr($this->nombre, 0, 1);
        $letraApellido = $this->apellidos !== null
            ? mb_substr($this->apellidos, 0, 1)
            : '';

        return mb_strtoupper($letraNombre . $letraApellido);
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre . ' ' . ($this->apellidos ?? ''));
    }
}