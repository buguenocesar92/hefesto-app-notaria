<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Una persona que aparece en alguna operacion de la notaria.
 *
 * AQUI NO HAY ROL.
 *
 * El rol (comprador, vendedor, Eulalia, albacea, testigo, heredero) NO es un
 * atributo de la persona: es de la RELACION entre la persona y el expediente.
 * Esta tabla guarda quien es la persona; el rol vive en
 * `expediente_partes`.
 *
 * La razon es concreta: si el rol estuviera aca, no se podria tener a la
 * misma persona como compradora en un expediente y como testigo en otro sin
 * duplicar el registro, y la duplicacion, con el tiempo, produce expedientes
 * cruzados. Un expediente cruzado en una notaria es un problema serio.
 *
 * DATOS PERSONALES: protegidos por la Ley 19.628. Ver docs/07.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Parte extends Model
{
    use HasUuids;

    protected $table = 'partes';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'apellidos',
        'rut',
        'correo',
        'telefono',
        'direccion',
        'observacion',
    ];

    public function expedientes(): BelongsToMany
    {
        return $this->belongsToMany(Expediente::class, 'expediente_partes', 'parte_id', 'expediente_id')
            ->withPivot(['id', 'rol', 'es_principal', 'observacion'])
            ->withTimestamps();
    }

    /** Los roles que esta persona tiene, agrupados por expediente. */
    public function roles(): \Illuminate\Support\Collection
    {
        return $this->expedientes()->get()->mapWithKeys(
            static fn (Expediente $expediente): array => [
                $expediente->id => $expediente->pivot->rol,
            ]
        );
    }

    /** Nombre completo, para mostrar en la interfaz. */
    public function nombreCompleto(): string
    {
        return trim($this->nombre . ' ' . ($this->apellidos ?? ''));
    }
}