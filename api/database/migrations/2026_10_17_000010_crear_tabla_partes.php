<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partes: las personas que aparecen en una operacion.
 *
 * UNA PERSONA, UNA FICHA
 * ----------------------
 * `partes` guarda quien es la persona, una sola vez para toda la notaria.
 * La misma persona puede aparecer en veinte expedientes.
 *
 * QUE NO HAY AQUI
 * ---------------
 * NO hay un campo que diga si es comprador, vendedor, Eulalia, albacea,
 * testigo o heredero. Ese dato NO es de la persona: es de la RELACION entre la
 * persona y el expediente. Si estuviera aqui, no se podria tener a Eulalia
 * como compradora en un expediente y como/testigo en otro sin duplicarla o
 * corregirla, y con el tiempo la duplicacion produce expedientes mal
 * cruzados.
 *
 * Por eso el rol vive en `expediente_partes`, que es tabla puente con rol.
 *
 * LA MISMA LOGICA APLICA A `solicitado_a` de antecedentes y al profesional
 * responsable del expediente: son atributos de la relacion, no de la persona.
 *
 * DATOS PERSONALES
 * ----------------
 * Esta tabla tiene datos personales protegidos por la Ley 19.628. Solo se
 * guardan los estrictamente necesarios para la operacion. Ver docs/07.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partes', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('notarias')->cascadeOnDelete();

            $tabla->string('nombre', 160);
            $tabla->string('apellidos', 160)->nullable();
            $tabla->string('rut', 20)->nullable();
            $tabla->string('correo', 190)->nullable();
            $tabla->string('telefono', 32)->nullable();
            $tabla->string('direccion', 250)->nullable();
            $tabla->text('observacion')->nullable();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'nombre']);
            $tabla->index(['tenant_id', 'rut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partes');
    }
};