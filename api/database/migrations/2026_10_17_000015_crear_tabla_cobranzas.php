<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobranzas: lo que se le registro al cliente.
 *
 * LO QUE ESTA TABLA NO HACE Y POR QUE
 * ----------------------------------
 * NO emite documentos tributarios electronicos.
 *
 * Hefesto tiene el kit LibreDTE, pero su version publicada es una BETA y su
 * API es de SOLO LECTURA: no permite automatizar la emision. Por lo tanto no
 * hay, en ninguna parte de esta aplicacion, codigo que emita, timbra,
 * anule ni envíe un documento tributario electronico. Prometerlo seria
 * prometer algo que no se puede hacer.
 *
 * Lo que queda es el REGISTRO: la notaria escribe aqui lo que ya emitio por
 * su cuenta, con su numero real, en `referencia_documento_tributario`. Es un
 * respaldo contable, no un emisor.
 *
 * Ver docs/05, seccion "Facturacion electronica: lo que no se hace".
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranzas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('notarias')->cascadeOnDelete();
            $tabla->foreignUuid('expediente_id')->nullable()->constrained('expedientes')->nullOnDelete();
            $tabla->foreignUuid('parte_id')->nullable()->constrained('partes')->nullOnDelete();

            $tabla->string('concepto', 200);
            $tabla->date('fecha_emision');
            $tabla->decimal('monto', 14, 2);
            $tabla->string('moneda', 3)->default('CLP');
            $tabla->string('estado', 20)->default('registrada');

            // Lo que la notaria ya emitio por su cuenta. Texto, no llave: el
            // documento tributario electronico no lo emite este sistema.
            $tabla->string('referencia_documento_tributario', 60)->nullable();
            $tabla->text('observacion')->nullable();

            $tabla->foreignUuid('registrado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'expediente_id']);
            $tabla->index(['tenant_id', 'estado']);
            $tabla->index(['tenant_id', 'fecha_emision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranzas');
    }
};