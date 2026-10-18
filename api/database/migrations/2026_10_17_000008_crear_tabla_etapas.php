<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapas del tramite de un expediente. AQUI VIVE EL SEMAFORO.
 *
 * Que es una etapa
 * ---------------
 * Una etapa es un paso del tramite que la notaria definio para su tipo de
 * operacion: "recepcion de antecedentes", "revision de documentos",
 * "firmas", "cierre". El nombre lo pone la notaria. Este sistema no conoce
 * las etapas de ninguna operacion en particular, y no las deduce.
 *
 * QUE ES EL SEMAFORO Y QUE NO ES
 * -------------------------------
 * El semaforo compara los dias que la etapa lleva en su estado actual contra
 * un umbral que la NOTARIA configuro (`dias_alerta`). Al superarlo, la etapa
 * se marca como atrasada.
 *
 * Lo que el semaforo ES:  una alarma interna de la notaria.
 * Lo que el semaforo NO ES: un plazo legal, un vencimiento, ni una
 *                         prediccion de cuando deberia estar listo.
 *
 * Un "rojo" NO significa "esto se vencio ante la ley". Significa "llevan N
 * dias en este estado y a la notaria le conviene mirar". La diferencia entre
 * las dos frases es la diferencia entre una herramienta y un riesgo.
 *
 * `dias_alerta` y `dias_critico` son NULL cuando la notaria no los definio.
 * Con los dos en NULL, `App\Services\Semaforo\SemaforoEtapa` devuelve el
 * nivel `sin_umbral` y NO marca nada. Es la conducta correcta: antes que
 * inventar un numero, no marcar.
 *
 * Si `dias_critico` es NULL pero `dias_alerta` no, el rojo nunca aparece: la
 * etapa queda en amarillo para siempre. Eso tambien es intencional.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etapas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('notarias')->cascadeOnDelete();
            $tabla->foreignUuid('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            $tabla->uuid('etapa_plantilla_id')->nullable();

            $tabla->unsignedSmallInteger('orden');
            $tabla->string('nombre', 160);
            $tabla->text('descripcion')->nullable();
            $tabla->string('estado', 24)->default('pendiente');
            $tabla->boolean('requiere_antecedente')->default(false);

            // Umbrales del semaforo, en dias. Configurables por la notaria.
            $tabla->unsignedSmallInteger('dias_alerta')->nullable();
            $tabla->unsignedSmallInteger('dias_critico')->nullable();

            $tabla->timestampTz('iniciada_en')->nullable();
            $tabla->timestampTz('terminada_en')->nullable();

            $tabla->foreignUuid('profesional_responsable_id')->nullable()->constrained('profesionales')->nullOnDelete();
            $tabla->foreignUuid('usuario_responsable_id')->nullable()->constrained('usuarios')->nullOnDelete();

            // Dias que lleva la etapa en su estado actual. Es un dato
            // DERIVADO: lo escribe el semaforo, no el usuario. Se guarda para
            // poder ordenar y filtrar en la base, y se recalcula en cada
            // corrida. Ver App\Services\Semaforo\RevisorDeSemaforo.
            $tabla->unsignedSmallInteger('dias_en_estado')->default(0);
            $tabla->string('nivel_semaforo', 16)->default('sin_umbral');
            $tabla->timestampTz('semaforo_calculado_en')->nullable();

            $tabla->timestampsTz();

            $tabla->unique(['expediente_id', 'orden']);
            $tabla->index(['tenant_id', 'estado']);
            $tabla->index(['tenant_id', 'nivel_semaforo']);
            $tabla->index(['tenant_id', 'estado', 'iniciada_en']);
            $tabla->index(['tenant_id', 'expediente_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etapas');
    }
};