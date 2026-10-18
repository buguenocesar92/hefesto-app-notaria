<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contadores por notaria: el folio interno.
 *
 * Solo para el `folio` interno de los expedientes, que es un identificador
 * de esta aplicacion y NO sale de ella.
 *
 * NO sirve para el numero de protocolo. Ese lo pone la notaria a mano, con el
 * criterio que le corresponda. Ver `expedientes.numero_protocolo`.
 *
 * Se incrementa dentro de la misma transaccion que inserta el expediente, con
 * `UPDATE ... RETURNING`, para que dos aperturas simultaneas no saquen el
 * mismo numero. La RLS protege que un tenant no lea ni escriba el contador de
 * otro.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contadores', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('notarias')->cascadeOnDelete();
            $tabla->string('clave', 40);
            $tabla->bigInteger('valor')->default(0);
            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contadores');
    }
};