<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesiones de Laravel.
 *
 * NO lleva `tenant_id` y NO lleva RLS: son las sesiones de la capa de sesion,
 * que tienen que poder escribirse antes de saber de que notaria viene la
 * persona. Es la misma excepcion que en el Portal de Autoservicio.
 *
 * Riesgo residual: quien tenga acceso SQL a esta tabla ve sesiones sin
 * decodificar. Mitigacion: el despliegue exige que el rol de la aplicacion no
 * sea superusuario ni tenga BYPASSRLS, y las sesiones van con el driver
 * `database` solo porque la API corre en varios contenedores. Ver docs/03,
 * seccion "El punto ciego".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $tabla): void {
            $tabla->string('id')->primary();
            $tabla->foreignUuid('user_id')->nullable()->index();
            $tabla->string('ip_address', 45)->nullable();
            $tabla->text('user_agent')->nullable();
            $tabla->longText('payload');
            $tabla->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};