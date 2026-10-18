<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AISLAMIENTO ENTRE NOTARIAS: Row Level Security (RLS) de PostgreSQL.
 *
 * Este archivo es el corazon del producto. Reutiliza el patron ya decidido y
 * documentado en `apps/portal-autoservicio/docs/03-MULTITENANCY.md`: columna
 * `tenant_id` mas RLS, con `WITH CHECK`, `FORCE ROW LEVEL SECURITY` y el
 * contexto viajando con `set_config(..., true)`, de alcance de transaccion.
 * No es un cuarto enfoque: es el mismo.
 *
 * POR QUE EN UNA NOTARIA IMPORTA MAS
 * ---------------------------------
 * En el portal de una PyME, que un cliente vea los datos de otro es un
 * problema comercial. En una notaria es distinto: son datos de patrimonio,
 * familia y sucesiones de personas identificables, protegidos por la Ley
 * 19.628, y una fuga no se corrige con un disculpo. El aislamiento va en la
 * base de datos, no en el codigo, por la misma razon pero con mas peso.
 *
 * MECANISMO
 * ----------
 * 1. `public.app_tenant_id()` lee el valor de la variable de sesion
 *    `app.tenant_id`. Si no esta definida, LANZA un error. No devuelve NULL:
 *    falla de forma ruidosa, para que quede cerrado.
 * 2. Cada tabla de negocio tiene una politica que exige
 *    `tenant_id = app_tenant_id()`, al leer (USING) y al escribir
 *    (WITH CHECK).
 * 3. `FORCE ROW LEVEL SECURITY` hace que la politica aplique tambien al
 *    dueno de la tabla. Sin FORCE, en desarrollo, donde el rol de la
 *    aplicacion suele ser el dueno, el aislamiento seria decorativo.
 * 4. La variable se fija con `set_config('app.tenant_id', ..., true)`, de
 *    alcance de TRANSACCION: al confirmar o revertir, PostgreSQL la descarta.
 *    Por eso un worker de colas, que reutiliza la misma conexion para
 *    trabajos de notarias distintas, no puede arrastrar el contexto ajeno.
 *
 * LA TABLA `notarias` NO TIENE RLS, a proposito: se consulta en el login,
 * cuando todavia no hay tenant de sesion. Se compensa con la vista
 * `vw_notarias_publicas`, que expone solo tres columnas.
 *
 * REQUISITO DE DESPLIEGUE: el rol de la aplicacion NO puede ser
 * superusuario ni tener BYPASSRLS. Con cualquiera de las dos cosas,
 * PostgreSQL ignora las politicas por completo. Lo comprueba:
 *   php artisan notaria:verificar-aislamiento
 */
return new class extends Migration
{
    /**
     * Tablas con datos de negocio, y por lo tanto con RLS.
     *
     * @var list<string>
     */
    private array $tablasProtegidas = [
        'usuarios',
        'profesionales',
        'configuracion_notaria',
        'tipos_operacion',
        'etapas_tipo_operacion',
        'expedientes',
        'etapas',
        'antecedentes',
        'partes',
        'expediente_partes',
        'documentos',
        'documento_versiones',
        'agenda_citas',
        'cobranzas',
        'alertas_semaforo',
        'bitacora_accesos',
        'contadores',
    ];

    public function up(): void
    {
        // 1. Funcion de contexto. SECURITY INVOKER (lo normal): no necesita
        //    privilegios propios.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.app_tenant_id() RETURNS uuid
            LANGUAGE plpgsql
            STABLE
            AS $$
            DECLARE
                valor text;
            BEGIN
                valor := current_setting('app.tenant_id', true);

                IF valor IS NULL OR valor = '' THEN
                    RAISE EXCEPTION USING
                        ERRCODE = 'insufficient_privilege',
                        MESSAGE = 'No hay contexto de notaria en la conexion (app.tenant_id no esta definida). Se aborta la operacion para no exponer datos de otra notaria.',
                        HINT = 'La peticion no paso por App\Http\Middleware\ResolverTenant, o la operacion se ejecuto fuera de una transaccion.';
                END IF;

                RETURN valor::uuid;
            END
            $$;
        SQL);

        DB::unprepared(<<<'SQL'
            COMMENT ON FUNCTION public.app_tenant_id() IS
            'Devuelve la notaria de la peticion. Lanza excepcion si no hay contexto: el aislamiento falla cerrado.';
        SQL);

        // 2. Politicas y activacion por tabla.
        foreach ($this->tablasProtegidas as $tabla) {
            $existe = DB::selectOne(
                'SELECT to_regclass(?) AS tabla',
                ['public.' . $tabla]
            );

            // to_regclass devuelve NULL si la tabla todavia no existe.
            if ($existe === null || $existe->tabla === null) {
                continue;
            }

            DB::unprepared('ALTER TABLE public.' . $tabla . ' ENABLE ROW LEVEL SECURITY');

            // FORCE hace que la politica aplique tambien al dueno. Es
            // indispensable: en desarrollo el dueno suele ser el mismo rol
            // que corre la aplicacion.
            DB::unprepared('ALTER TABLE public.' . $tabla . ' FORCE ROW LEVEL SECURITY');

            $nombre = 'politica_aislamiento_' . $tabla;

            DB::unprepared(<<<SQL
                DROP POLICY IF EXISTS {$nombre} ON public.{$tabla};

                CREATE POLICY {$nombre} ON public.{$tabla}
                    FOR ALL
                    TO PUBLIC
                    USING (tenant_id = public.app_tenant_id())
                    WITH CHECK (tenant_id = public.app_tenant_id());
            SQL);
        }

        // 3. Fallo ruidoso si alguna tabla de la lista quedo sin `tenant_id`.
        //    Sin esto, un error de tipeo dejaria una tabla de datos de
        //    notaria sin politica y la migracion passaria igual.
        $this->verificarColumnaTenant();
    }

    public function down(): void
    {
        foreach ($this->tablasProtegidas as $tabla) {
            $existe = DB::selectOne('SELECT to_regclass(?) AS tabla', ['public.' . $tabla]);

            if ($existe === null || $existe->tabla === null) {
                continue;
            }

            DB::unprepared('DROP POLICY IF EXISTS politica_aislamiento_' . $tabla . ' ON public.' . $tabla);
            DB::unprepared('ALTER TABLE public.' . $tabla . ' NO FORCE ROW LEVEL SECURITY');
            DB::unprepared('ALTER TABLE public.' . $tabla . ' DISABLE ROW LEVEL SECURITY');
        }

        DB::unprepared('DROP FUNCTION IF EXISTS public.app_tenant_id()');
    }

    /**
     * Falla ruidosamente si alguna tabla de la lista no tiene `tenant_id`.
     */
    private function verificarColumnaTenant(): void
    {
        foreach ($this->tablasProtegidas as $tabla) {
            $existe = DB::selectOne(
                'SELECT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = ''public'' AND table_name = ? AND column_name = ''tenant_id''
                 ) AS tiene_columna',
                [$tabla]
            );

            if ($existe === null || ! $existe->tiene_columna) {
                throw new RuntimeException(
                    'La tabla public.' . $tabla . ' no tiene columna tenant_id y no puede protegerse con RLS. '
                    . 'Revise la migracion antes de continuar.'
                );
            }
        }
    }
};