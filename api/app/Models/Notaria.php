<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * La notaria: el tenant de esta aplicacion.
 *
 * Tabla SIN Row Level Security, a proposito: se consulta en el login, cuando
 * todavia no hay sesion y por lo tanto no hay `app.tenant_id` que poner. Es
 * la excepcion consciente documentada en docs/03.
 *
 * La compensacion es doble:
 *   1. Antes del login la aplicacion no lee esta tabla crudo, sino la vista
 *      `vw_notarias_publicas`, que expone solo slug, nombre y activo.
 *   2. El rol de la aplicacion no es superusuario ni tiene BYPASSRLS, y eso
 *      lo comprueba `notaria:verificar-aislamiento`.
 */
class Notaria extends Model
{
    use HasUuids;

    protected $table = 'notarias';

    protected $fillable = [
        'slug',
        'nombre',
        'rut',
        'comuna',
        'direccion',
        'correo_contacto',
        'telefono_contacto',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}