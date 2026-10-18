<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notarias: el tenant de esta aplicacion.
 *
 * UNA INSTALACION = VARIAS NOTARIAS
 * ----------------------------------
 * La tabla `notarias` es el equivalente de `tenants` en el Portal de
 * Autoservicio y usa el mismo patron de aislamiento (columna `tenant_id` +
 * Row Level Security). Ver docs/03.
 *
 * A diferencia de una PyME, una notaria es una OFICINA DEL ESTADO. Por eso la
 * tabla guarda el RUT de la notaria y el slug con que se enters, pero NO
 * guarda ningun dato que el sistema haya deducido: lo que se registra es lo
 * que la propia notaria digito.
 *
 * AISLAMIENTO: esta tabla NO lleva RLS, a proposito. Se consulta antes del
 * login, cuando todavia no hay contexto de tenant. Se compensa con la vista
 * `vw_notarias_publicas`, que expone solo tres columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notarias', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->string('slug', 80)->unique();
            $tabla->string('nombre', 160);
            $tabla->string('rut', 20)->nullable();
            $tabla->string('comuna', 80)->nullable();
            $tabla->string('direccion', 250)->nullable();
            $tabla->string('correo_contacto', 190)->nullable();
            $tabla->string('telefono_contacto', 32)->nullable();
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notarias');
    }
};