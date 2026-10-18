<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alertas del semaforo: el registro de que el sistema se dio cuenta solo.
 *
 * Que una etapa este en rojo HOY no es informacion: depende del momento en
 * que se mire. Lo que sirve es que HAYA pasado, y cuando. Esta tabla guarda
 * esa historia, y es la que permite que el tablero muestre "esta etapa lleva
 * 12 dias en rojo desde el 3 de octubre" y que la notaria vea si el problema
 * es de hoy o de hace una semana.
 *
 * LA REGLA DE UNA ALERTA ABIERTA POR ETAPA Y NIVEL
 * -------------------------------------------------
 * Una etapa no acumula diez alertas rojas porque se corrio el semaforo diez
 * veces. Se abre UNA alerta cuando la etapa cruza el umbral, y mientras siga
 * en ese nivel la alerta se ACTUALIZA (suben los dias y cambia la ultima
 * revision). Si la etapa baja a verde y vuelve a subir, se abre una nueva
 * alerta, que es un hecho distinto y merece su propio registro.
 *
 * El indice unico de mas abajo impone esa regla en la base, no solo en PHP:
 * si dos corridas se pisan, la base rechaza el duplicado.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas_semaforo', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('notarias')->cascadeOnDelete();
            $tabla->foreignUuid('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            $tabla->foreignUuid('etapa_id')->constrained('etapas')->cascadeOnDelete();

            $tabla->string('nivel', 16);
            $tabla->unsignedSmallInteger('dias_en_estado')->default(0);
            $tabla->unsignedSmallInteger('umbral_dias')->nullable();
            $tabla->timestampTz('umbral_cruzado_en');
            $tabla->timestampTz('ultima_revision_en');
            $tabla->unsignedSmallInteger('veces_revisada')->default(1);

            $tabla->foreignUuid('cerrada_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestampTz('cerrada_en')->nullable();
            $tabla->string('motivo_cierre', 250)->nullable();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'nivel']);
            $tabla->index(['tenant_id', 'expediente_id']);
            $tabla->index(['tenant_id', 'cerrada_en']);
        });

        // Una sola alerta ABIERTA por etapa y nivel.
        //
        // `cerrada_en IS NULL` es un indice PARCIAL: PostgreSQL solo lo
        // aplica a las filas que cumple la condicion, asi que el mismo par
        // (etapa, nivel) puede repetirse sin problema una vez que la alerta
        // anterior esta cerrada, pero no puede tener dos abiertas a la vez.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX alertas_semaforo_abierta_por_etapa_nivel
                ON public.alertas_semaforo (etapa_id, nivel)
                WHERE cerrada_en IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS public.alertas_semaforo_abierta_por_etapa_nivel');

        Schema::dropIfExists('alertas_semaforo');
    }
};