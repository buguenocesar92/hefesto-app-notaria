<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Puente entre expediente y parte, CON el rol adentro.
 *
 * Esta tabla es la que hace que el rol sea parte de la relacion. El mismo
 * `par` (expediente, persona) puede tener dos roles distintos si hace falta,
 * y la misma persona puede tener roles distintos en expedientes distintos.
 *
 * `rol` es texto y lo define la notaria. El sistema trae solo algunos
 * sugeridos en el formulario; no restringe la lista, porque la lista de roles
 * que una operacion reconoce depende de la operacion y del marco aplicable,
 * y ese marco no lo conoce esta aplicacion.
 *
 * `es_principal` marca al interlocutor principal de esa operacion. Es una
 * comodidad de la agenda, no una verdad legal.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expediente_partes', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('notarias')->cascadeOnDelete();
            $tabla->foreignUuid('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            $tabla->foreignUuid('parte_id')->constrained('partes')->cascadeOnDelete();

            // EL ROL ESTA ACA, no en `partes`.
            $tabla->string('rol', 40);
            $tabla->boolean('es_principal')->default(false);
            $tabla->text('observacion')->nullable();

            $tabla->timestampsTz();

            $tabla->unique(['expediente_id', 'parte_id', 'rol']);
            $tabla->index(['tenant_id', 'parte_id']);
            $tabla->index(['tenant_id', 'expediente_id', 'rol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expediente_partes');
    }
};