<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antecedentes: lo que hay que conseguir para poder avanzar.
 *
 * POR QUE EXISTE ESTA TABLA
 * -------------------------
 * El problema real que consume tiempo en una notaria no es escribir: es que
 * un expediente se quede parado porque nadie fue a buscar un antecedente.
 * "Pedido aX el dia 3", "nunca llego", "el cliente no sabe de que hablamos".
 *
 * Por eso el antecedente tiene su propio registro, con a quien se le pidio,
 * cuando se pidio, y cuando llego. Y por eso la etapa que lo necesita puede
 * quedar esperando: si esta `requiere_antecedente`, el semaforo cuenta los
 * dias de espera de esa etapa.
 *
 * `solicitado_a` es texto libre a proposito. Se pide a un registro que puede
 * no estar en el sistema (otro notario, un municipal, un archivo), asi que
 * no puede ser una llave foranea.
 *
 * `estado` refleja lo que la notaria registro, no lo que la ley exige.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('antecedentes', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('notarias')->cascadeOnDelete();
            $tabla->foreignUuid('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            $tabla->uuid('etapa_id')->nullable();

            $tabla->string('descripcion', 250);
            $tabla->string('solicitado_a', 200)->nullable();
            $tabla->string('estado', 24)->default('pendiente');
            $tabla->foreignUuid('responsable_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->date('solicitado_en')->nullable();
            $tabla->date('recibido_en')->nullable();
            $tabla->text('respuesta')->nullable();
            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'expediente_id']);
            $tabla->index(['tenant_id', 'estado']);
            $tabla->index(['tenant_id', 'responsable_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('antecedentes');
    }
};