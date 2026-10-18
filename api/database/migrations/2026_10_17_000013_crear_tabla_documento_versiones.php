<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versiones de un documento: el ARCHIVO de cada version.
 *
 * Cada vez que se sube un archivo, se agrega una fila. Nunca se edita una
 * fila existente: si el archivo estaba mal, se sube otra version y la
 * anterior queda con `vigente_hasta` distinto de NULL.
 *
 * `vigente_hasta` en NULL significa "esta es la version vigente". Es una
 * convencion de la aplicacion, y permite encontrar rapido cual era la
 * vigente sin tener que mirar `documentos.version_actual`.
 *
 * `ruta_archivo` es RELATIVA al volumen de documentos. El nombre original se
 * guarda aparte en `nombre_archivo` y es lo que se descarga.
 *
 * `hash_sha256` detecta alteracion del archivo en disco y sirve para validar
 * un respaldo restaurado. No es una firma criptografica: solo detecta
 * corrupcion y cambios accidentales.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_versiones', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('notarias')->cascadeOnDelete();
            $tabla->foreignUuid('documento_id')->constrained('documentos')->cascadeOnDelete();

            $tabla->unsignedSmallInteger('version');
            $tabla->date('fecha_documento')->nullable();
            $tabla->string('ruta_archivo', 255);
            $tabla->string('nombre_archivo', 190);
            $tabla->string('mime', 120)->nullable();
            $tabla->unsignedBigInteger('tamano_bytes')->nullable();
            $tabla->char('hash_sha256', 64)->nullable();

            $tabla->foreignUuid('subido_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestampTz('vigente_hasta')->nullable();
            $tabla->text('observacion')->nullable();

            $tabla->timestampsTz();

            $tabla->unique(['documento_id', 'version']);
            $tabla->index(['tenant_id', 'documento_id']);
            $tabla->index(['tenant_id', 'vigente_hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_versiones');
    }
};