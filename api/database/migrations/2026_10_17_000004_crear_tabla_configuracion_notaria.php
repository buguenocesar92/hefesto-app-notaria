<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuracion de la notaria: los valores que la notaria decide.
 *
 * LA ADVERTENCIA QUE DEFINE ESTA TABLA
 * ------------------------------------
 * Este sistema NO reemplaza ni interpreta la ley. No puede: no es su
 * competencia. Por eso los parametros que se guardan aqui son
 *
 *   (a) UMBRALES INTERNOS DE LA NOTARIA, que la notaria se fija para
 *       controlarse a si misma (cuantos dias espera un antecedente antes de
 *       alarmarse), y
 *   (b) ESPACIOS VACIOS para que un notario cargue, el mismo, el dato y el
 *       fundamento que corresponda.
 *
 * Lo que este sistema NO hace, en ningun caso:
 *   - No trae plazos legales por omision.
 *   - No deduce cuando un tramite "corresponde" estar listo.
 *   - No avisa "esto se vencio ante la ley".
 *
 * `parametros_por_confirmar` arranca con las CLAVES y sin valores. El valor
 * lo carga un notario. Si se deja vacio, el sistema se niega a calcular, que
 * es justo lo que hay que hacer cuando no se sabe.
 *
 * `dias_alerta_por_defecto` y `dias_critico_por_defecto` vienen en NULL a
 * proposito. Sin umbral configurado, el semaforo NO marca nada: no inventa.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_notaria', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->unique()->constrained('notarias')->cascadeOnDelete();

            $tabla->string('zona_horaria', 64)->default('America/Santiago');

            // Umbrales internos del semaforo. NULL = la notaria todavia no los
            // definio, y entonces el semaforo no marca nada.
            $tabla->unsignedSmallInteger('dias_alerta_por_defecto')->nullable();
            $tabla->unsignedSmallInteger('dias_critico_por_defecto')->nullable();

            // Texto que se muestra en pantalla cuando falta el umbral, para
            // que quede claro que NO es un vencimiento sino una falta de
            // configuracion.
            $tabla->string('aviso_umbral_no_configurado', 250)->nullable();

            /**
             * Espacios vacios para datos que la notaria debe confirmar.
             *
             * Estructura por clave:
             *   etiqueta  : que es este dato
             *   valor     : null hasta que un notario lo cargue
             *   fundamento: con que norma o criterio se fijo (lo carga el
             *               notario, no esta aplicacion)
             *   confirmado_por / confirmado_en: quien lo confirmo y cuando
             *
             * `valor` y `fundamento` NUNCA se siembran con un valor de
             * ejemplo. Sembrarlos seria inventar un plazo.
             */
            $tabla->jsonb('parametros_por_confirmar')->nullable();

            $tabla->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_notaria');
    }
};