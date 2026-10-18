<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos del expediente: la FICHA LOGICA.
 *
 * UN EXPEDIENTE SIN SU HISTORIAL DOCUMENTAL NO VALE NADA
 * -------------------------------------------------------
 * En una notaria, la question "que documento estaba vigente cuando se firmo"
 * no es un detalle: es el expediente. Por eso este sistema tiene una regla
 * que no es negociable:
 *
 *   NUNCA SE BORRA UN DOCUMENTO ASOCIADO A UN EXPEDIENTE.
 *
 * Lo que se hace es MARCARLO COMO REEMPLAZADO. El registro queda, con la
 * fecha en que dejo de estar vigente y con quien lo reemplazo.
 *
 * Aqui vive la identidad logica del documento (de que expediente es, como se
 * llama, si esta vigente). Cada archivo concreto es una fila de
 * `documento_versiones`. Un documento con tres versiones tiene tres filas
 * alla y una fila aqui, en estado `reemplazado`.
 *
 * `tipo` lo define la notaria. El sistema no impone un catalogo de tipos de
 * documento notarial, porque no conoce el marco aplicable.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('notarias')->cascadeOnDelete();
            $tabla->foreignUuid('expediente_id')->constrained('expedientes')->cascadeOnDelete();

            $tabla->string('nombre', 200);
            $tabla->string('tipo', 40)->nullable();
            $tabla->string('estado', 20)->default('vigente');
            $tabla->unsignedSmallInteger('version_actual')->default(1);
            $tabla->date('fecha_documento')->nullable();
            $tabla->text('observacion')->nullable();

            $tabla->foreignUuid('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            // Quien marco el documento como reemplazado, y cuando.
            $tabla->foreignUuid('reemplazado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestampTz('reemplazado_en')->nullable();
            $tabla->uuid('reemplazado_por_documento_id')->nullable();

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'expediente_id', 'nombre', 'version_actual']);
            $tabla->index(['tenant_id', 'expediente_id']);
            $tabla->index(['tenant_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};