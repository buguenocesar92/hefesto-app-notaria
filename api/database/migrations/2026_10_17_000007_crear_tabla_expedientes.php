<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expedientes: el CENTRO del sistema.
 *
 * Un expediente es una operacion en curso. De el cuelgan las etapas, los
 * antecedentes que hay que conseguir, las partes, los documentos y lo
 * facturado. Si hay que explicar este sistema en una frase, es esto.
 *
 * SOBRE `numero_protocolo`
 * ------------------------
 * El numero de protocolo lo lleva la notaria y lo coloca a mano. Este
 * sistema NO genera numeros de protocolo: un numero mal lleva en una
 * notaria es un problema administrativo serio, y lo peor que un sistema
 * puede hacer es inventarlos. Lo unico que el sistema genera es el `folio`
 * interno, que no sale de aqui.
 *
 * `estado` es el estado GLOBAL del tramite. El estado fino de cada etapa vive
 * en `etapas.estado`. El global se calcula, no se escribe a mano: ver
 * `App\Services\Expedientes\EstadoDeExpediente`.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expedientes', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('notarias')->cascadeOnDelete();
            $tabla->string('folio', 24);
            $tabla->string('numero_protocolo', 60)->nullable();
            $tabla->foreignUuid('tipo_operacion_id')->constrained('tipos_operacion')->restrictOnDelete();
            $tabla->foreignUuid('profesional_responsable_id')->nullable()->constrained('profesionales')->nullOnDelete();
            $tabla->foreignUuid('usuario_responsable_id')->nullable()->constrained('usuarios')->nullOnDelete();

            $tabla->string('estado', 24)->default('en_tramite');
            $tabla->string('descripcion', 250)->nullable();
            $tabla->text('detalle')->nullable();

            $tabla->date('fecha_inicio');
            $tabla->date('fecha_cierre')->nullable();

            // Snapshot del umbral al abrir el expediente. Copia lo que la
            // plantilla o la notaria tenia en ese momento, para que cambiar
            // el valor por defecto despues no reescriba expedientes abiertos.
            $tabla->unsignedSmallInteger('dias_alerta_por_defecto')->nullable();
            $tabla->unsignedSmallInteger('dias_critico_por_defecto')->nullable();

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'folio']);
            $tabla->index(['tenant_id', 'estado']);
            $tabla->index(['tenant_id', 'tipo_operacion_id']);
            $tabla->index(['tenant_id', 'profesional_responsable_id']);
            $tabla->index(['tenant_id', 'fecha_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expedientes');
    }
};