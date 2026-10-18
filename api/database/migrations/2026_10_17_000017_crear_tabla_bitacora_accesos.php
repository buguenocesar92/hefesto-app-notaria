<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitacora de acceso: quien entro, a que expediente y desde donde.
 *
 * Se escribe en modo SOLO AGREGAR desde `App\Http\Middleware\AuditarAcceso`.
 * No hay endpoint para editarla ni borrarla. Se consulta por consola o por
 * el panel del proveedor de Hosting. Ver docs/07, seccion "Trazabilidad".
 *
 * `expediente_id` se anota cuando la peticion se hizo sobre un expediente:
 * el caso importante es "quien abrio este expediente y cuando", que es lo
 * que se le pregunta a una notaria si un tercero reclama.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora_accesos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('notarias')->cascadeOnDelete();
            $tabla->foreignUuid('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->uuid('expediente_id')->nullable();

            $tabla->string('accion', 40);
            $tabla->string('entidad', 80)->nullable();
            $tabla->uuid('entidad_id')->nullable();
            $tabla->string('metodo_http', 8)->nullable();
            $tabla->string('ruta', 255)->nullable();
            $tabla->string('direccion_ip', 45)->nullable();
            $tabla->string('agente_usuario', 255)->nullable();
            $tabla->boolean('exito')->default(true);
            $tabla->timestampTz('registrado_en');

            $tabla->index(['tenant_id', 'registrado_en']);
            $tabla->index(['tenant_id', 'expediente_id', 'registrado_en']);
            $tabla->index(['tenant_id', 'usuario_id', 'registrado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_accesos');
    }
};