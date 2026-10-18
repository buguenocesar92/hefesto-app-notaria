<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantilla de etapas por tipo de operacion.
 *
 * Son las etapas TIPICAS que la notaria definió para ese tipo de operacion.
 * Al abrir un expediente se copian a `etapas`. Por eso existen las dos
 * tablas: la plantilla se puede editar sin tocar los expedientes ya abiertos,
 * y cada expediente conserva las etapas con las que se abrio.
 *
 * `dias_alerta` y `dias_critico` de la plantilla tambien se copian a la
 * etapa del expediente, y quedan editables expediente por expediente.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etapas_tipo_operacion', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('notarias')->cascadeOnDelete();
            $tabla->foreignUuid('tipo_operacion_id')->constrained('tipos_operacion')->cascadeOnDelete();
            $tabla->unsignedSmallInteger('orden');
            $tabla->string('nombre', 160);
            $tabla->text('descripcion')->nullable();
            $tabla->boolean('requiere_antecedente')->default(false);
            $tabla->unsignedSmallInteger('dias_alerta')->nullable();
            $tabla->unsignedSmallInteger('dias_critico')->nullable();
            $tabla->timestampsTz();

            $tabla->unique(['tipo_operacion_id', 'orden']);
            $tabla->index(['tenant_id', 'tipo_operacion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etapas_tipo_operacion');
    }
};