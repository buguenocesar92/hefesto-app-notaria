<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de operacion que atiende cada notaria.
 *
 * SON CONFIGURABLES POR NOTARIA, Y ESO ES LO IMPORTANTE
 * ------------------------------------------------------
 * El catalogo NO viene impuesto. Cada notaria define los tipos de operacion
 * que atiende, con el nombre que ella usa. Este sistema no sabe que es una
 * compraventa, ni una sucesion, ni cuanto dura una, ni que documentos
 * exigen: eso lo carga la notaria.
 *
 * `dias_alerta_por_defecto` y `dias_critico_por_defecto` son el umbral que
 * se copia a las etapas nuevas de este tipo de operacion. NULL = sin umbral.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_operacion', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('notarias')->cascadeOnDelete();
            $tabla->string('codigo', 40);
            $tabla->string('nombre', 160);
            $tabla->text('descripcion')->nullable();
            $tabla->string('color', 16)->nullable();
            $tabla->unsignedSmallInteger('duracion_atencion_minutos')->nullable();
            $tabla->unsignedSmallInteger('dias_alerta_por_defecto')->nullable();
            $tabla->unsignedSmallInteger('dias_critico_por_defecto')->nullable();
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'codigo']);
            $tabla->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_operacion');
    }
};