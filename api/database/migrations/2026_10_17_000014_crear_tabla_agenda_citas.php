<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda: citas y bloqueos, por profesional.
 *
 * `estado` distingue una atencion de un bloqueo:
 *   - `bloqueo` es tiempo que NO se puede agendar (reunion interna,
 *     perpetual, alimentacion). No tiene expediente.
 *   - los demas estados son atenciones a una persona, y pueden o no estar
 *     pegadas a un expediente.
 *
 * `duracion_minutos` es la duracion prevista. Se calcula a mano o se propone
 * desde el tipo de operacion. NO es un plazo legal: es quanto dura la reunion.
 *
 * La agenda no decide si algo es valido. Solo reserva tiempo.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_citas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('notarias')->cascadeOnDelete();
            $tabla->foreignUuid('profesional_id')->constrained('profesionales')->cascadeOnDelete();
            $tabla->foreignUuid('expediente_id')->nullable()->constrained('expedientes')->nullOnDelete();
            $tabla->foreignUuid('tipo_operacion_id')->nullable()->constrained('tipos_operacion')->nullOnDelete();

            $tabla->string('titulo', 160);
            $tabla->string('estado', 20)->default('confirmada');
            $tabla->timestampTz('inicio');
            $tabla->timestampTz('fin');
            $tabla->unsignedSmallInteger('duracion_minutos')->nullable();
            $tabla->string('bloqueo', 40)->nullable();
            $tabla->text('notas')->nullable();

            $tabla->foreignUuid('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'profesional_id', 'inicio']);
            $tabla->index(['tenant_id', 'inicio', 'fin']);
            $tabla->index(['tenant_id', 'expediente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_citas');
    }
};