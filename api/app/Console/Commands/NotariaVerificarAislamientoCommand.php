<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tenancy\ContextoTenant;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Comprueba que el aislamiento entre notarias esta realmente puesto.
 *
 * POR QUE ESTE COMANDO EXISTE
 * ---------------------------
 * El aislamiento no es solo el codigo que escribimos: es el ESTADO en que
 * quedo la base de datos. Un despliegue bien intencionado pero mal
 * configurado (un rol con BYPASSRLS, una politica borrada a mano, una tabla
 * nueva sin proteger) deja la aplicacion funcionando y, al mismo tiempo,
 * completamente abierta entre notarias. Nada en la interfaz lo delata.
 *
 * En una notaria eso significa una fuga de datos de patrimonio, familia y
 * sucesiones de personas identificables. Por eso este comando se ejecuta en el
 * despliegue y devuelve codigo de salida 1 si algo falla.
 *
 *   php artisan notaria:verificar-aislamiento
 */
class NotariaVerificarAislamientoCommand extends Command
{
    /** @var list<string> */
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

    protected $signature = 'notaria:verificar-aislamiento';

    protected $description = 'Comprueba que la RLS, el rol de base de datos y el contexto de notaria protegen los datos de cada notaria.';

    public function handle(): int
    {
        $fallos = [];

        $fallos = array_merge($fallos, $this->revisarRol());
        $fallos = array_merge($fallos, $this->revisarTablas());
        $fallos = array_merge($fallos, $this->revisarFuncionDeContexto());
        $fallos = array_merge($fallos, $this->revisarLecturaAislada());
        $fallos = array_merge($fallos, $this->revisarVistaPublica());

        if ($fallos !== []) {
            $this->error('El aislamiento entre notarias NO esta bien puesto:');

            foreach ($fallos as $fallo) {
                $this->line('  - ' . $fallo);
            }

            $this->line('');
            $this->line('No sigas con el despliegue. Revisa la migracion');
            $this->line('database/migrations/2026_10_17_000021_activar_row_level_security.php');

            return self::FAILURE;
        }

        $this->info('Aislamiento entre notarias verificado correctamente.');

        return self::SUCCESS;
    }

    /**
     * El rol de la aplicacion no puede ser superusuario ni tener BYPASSRLS.
     *
     * Con cualquiera de las dos cosas, PostgreSQL ignora las politicas RLS
     * por completo.
     *
     * @return list<string>
     */
    private function revisarRol(): array
    {
        $fila = DB::selectOne(
            'SELECT r.rolname AS nombre, r.rolsuper AS superusuario, r.rolbypassrls AS salta_rls
             FROM pg_roles r
             WHERE r.rolname = current_user'
        );

        if ($fila === null) {
            return ['No se pudo identificar el rol de la aplicacion en PostgreSQL.'];
        }

        $fallos = [];

        if ((bool) $fila->superusuario) {
            $fallos[] = sprintf(
                'El rol "%s" es SUPERUSUARIO: ignora todas las politicas RLS.',
                $fila->nombre
            );
        }

        if ((bool) $fila->salta_rls) {
            $fallos[] = sprintf(
                'El rol "%s" tiene BYPASSRLS: ignora todas las politicas RLS.',
                $fila->nombre
            );
        }

        if ($fallos === []) {
            $this->line('  [ok] Rol "' . $fila->nombre . '": sin superusuario y sin BYPASSRLS.');
        }

        return $fallos;
    }

    /**
     * Cada tabla de negocio tiene RLS activada, forzada y con politica.
     *
     * @return list<string>
     */
    private function revisarTablas(): array
    {
        $fallos = [];

        foreach ($this->tablasProtegidas as $tabla) {
            $estado = DB::selectOne(
                'SELECT c.relrowsecurity AS rls,
                        c.relforcerowsecurity AS forzada
                 FROM pg_class c
                 WHERE c.oid = to_regclass(?)',
                ['public.' . $tabla]
            );

            if ($estado === null) {
                $fallos[] = 'La tabla public.' . $tabla . ' no existe. Falto correr las migraciones.';

                continue;
            }

            if (! $estado->rls) {
                $fallos[] = 'La tabla public.' . $tabla . ' NO tiene Row Level Security activada.';

                continue;
            }

            if (! $estado->forzada) {
                $fallos[] = 'La tabla public.' . $tabla . ' tiene RLS pero NO esta forzada '
                    . '(FORCE ROW LEVEL SECURITY): el dueno de la tabla saltaria la politica.';

                continue;
            }

            $politicas = DB::selectOne(
                'SELECT count(*) AS total
                 FROM pg_policies
                 WHERE schemaname = ? AND tablename = ?',
                ['public', $tabla]
            );

            if ((int) ($politicas->total ?? 0) === 0) {
                $fallos[] = 'La tabla public.' . $tabla . ' tiene RLS activada pero no tiene ninguna politica.';
            }
        }

        if ($fallos === []) {
            $this->line('  [ok] ' . count($this->tablasProtegidas) . ' tablas con RLS activada, forzada y con politica.');
        }

        return $fallos;
    }

    /**
     * Sin contexto de notaria, la funcion de contexto tiene que FALLAR.
     *
     * Si devolviera NULL, una consulta sin contexto devolveria cero filas por
     * accidente en vez de por diseno, y en el futuro alguien podria
     * "arreglarlo" asi y abrir la puerta sin darse cuenta.
     *
     * @return list<string>
     */
    private function revisarFuncionDeContexto(): array
    {
        try {
            DB::select('SELECT public.app_tenant_id()');
        } catch (QueryException) {
            $this->line('  [ok] public.app_tenant_id() falla sin contexto de notaria (aislamiento cerrado).');

            return [];
        }

        return [
            'public.app_tenant_id() devolvio un valor sin que haya contexto de notaria. '
            . 'Deberia lanzar excepcion para que el aislamiento falle cerrado.',
        ];
    }

    /**
     * Con una notaria que no existe, las tablas deben salir vacias y el
     * contexto no debe quedar colgando.
     *
     * @return list<string>
     */
    private function revisarLecturaAislada(): array
    {
        $notariaInexistente = (string) Str::uuid();
        $contexto = new ContextoTenant();
        $fallos = [];

        try {
            $conteo = $contexto->ejecutarAislado(
                $notariaInexistente,
                fn (): int => DB::table('expedientes')->count()
            );
        } catch (QueryException $excepcion) {
            return ['No se pudo leer "expedientes" con un contexto de notaria: ' . $excepcion->getMessage()];
        }

        if ($conteo !== 0) {
            $fallos[] = 'La tabla "expedientes" devolvio ' . $conteo . ' filas para una notaria inexistente.';
        }

        $residuo = $contexto->tenantIdEnConexion();

        if ($residuo !== null) {
            $fallos[] = 'La variable app.tenant_id quedo fijada (' . $residuo . ') despues de la transaccion. '
                . 'Si queda colgando, el worker de colas podria leer datos de otra notaria.';
        }

        if ($fallos === []) {
            $this->line('  [ok] Con una notaria inexistente no se lee ninguna fila y el contexto no queda colgando.');
        }

        return $fallos;
    }

    /**
     * La vista publica debe exponer SOLO las tres columnas acordadas.
     *
     * @return list<string>
     */
    private function revisarVistaPublica(): array
    {
        $columnas = DB::select(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'vw_notarias_publicas'
             ORDER BY column_name"
        );

        if ($columnas === []) {
            return ['No existe la vista public.vw_notarias_publicas.'];
        }

        $nombres = array_map(static fn ($fila): string => (string) $fila->column_name, $columnas);
        sort($nombres);

        $esperadas = ['activo', 'nombre', 'slug'];
        sort($esperadas);

        if ($nombres !== $esperadas) {
            return ['La vista public.vw_notarias_publicas expone columnas distintas de las tres esperadas ('
                . implode(', ', $nombres) . '). No debe exponer datos de contacto.'];
        }

        $this->line('  [ok] La vista publica de notarias expone solo slug, nombre y activo.');

        return [];
    }
}