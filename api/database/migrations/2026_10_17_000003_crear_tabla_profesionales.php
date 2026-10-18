<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profesionales que reciben agenda: notarios, matrizes, protocolistas.
 *
 * POR QUE ES UNA TABLA APARTE DE `usuarios`
 * ------------------------------------------
 * En una notaria no todas las personas que atienden tienen cuenta de
 * usuario, y no todas las cuentas de usuario atienden. El auxiliar que
 * ordena la agenda puede no necesitar entrar nunca; el notario adjoint puede
 * entrar y no tener agenda propia.
 *
 * `cargo` describe el cargo en la notaria. El sistema NO interpreta el cargo:
 * no sabe que puede o que no puede hacer un notario, eso lo define el
 * administrador de la notaria y el marco legal aplicable, no esta aplicacion.
 *
 * `usuario_id` es opcional: un profesional puede tener agenda sin cuenta.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesionales', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('notarias')->cascadeOnDelete();
            $tabla->foreignUuid('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->string('nombre', 80);
            $tabla->string('apellidos', 80)->nullable();
            $tabla->string('cargo', 40)->default('auxiliar');
            $tabla->string('correo', 190)->nullable();
            $tabla->string('telefono', 32)->nullable();
            // Duracion de una atencion tipica, en minutos. Es una ayuda para
            // proposear horarios de agenda; no es un plazo legal.
            $tabla->unsignedSmallInteger('duracion_atencion_minutos')->nullable();
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'activo']);
            $tabla->index(['tenant_id', 'cargo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profesionales');
    }
};