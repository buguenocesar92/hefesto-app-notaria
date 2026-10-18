<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usuarios de la notaria: las personas que inician sesion.
 *
 * La identidad NO se guarda aqui: se guarda el `authentik_sub`, que es el
 * identificador del sujeto en Authentik. La contrasena nunca esta en esta
 * aplicacion.
 *
 * `rol` es el permiso DENTRO de la notaria. No es lo mismo que el grupo de
 * Authentik: el grupo de Authentik sirve para el primer alta, y de ahi en
 * adelante manda este rol. Ver docs/03, seccion "Roles".
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('notarias')->cascadeOnDelete();
            $tabla->uuid('authentik_sub')->unique();
            $tabla->string('nombre', 80);
            $tabla->string('apellidos', 80)->nullable();
            $tabla->string('correo', 190);
            $tabla->string('telefono', 32)->nullable();
            $tabla->string('rol', 24)->default('auxiliar');
            $tabla->boolean('activo')->default(true);
            $tabla->timestampTz('ultimo_acceso')->nullable();
            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'rol']);
            $tabla->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};