<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Contexto de notaria de la peticion.
 *
 * La RLS de PostgreSQL (migracion 2026_10_17_000021) lee la notaria desde la
 * variable de sesion `app.tenant_id`. Esta clase es la UNICA que la escribe.
 *
 * MISMO PATRON, MISMO MOTIVO
 * --------------------------
 * Es el patron definido en `apps/portal-autoservicio/docs/03-MULTITENANCY.md`,
 * seccion 3.3, reutilizado tal cual: `set_config(..., true)`, o sea alcance
 * de TRANSACCION.
 *
 * El `true` final es lo mas importante de esta clase. Al confirmar o
 * revertir la transaccion, PostgreSQL descarta el valor solo. Con un `SET`
 * normal (alcance de sesion), el valor sobreviviria a la peticion, y el
 * worker de colas, que REUTILIZA LA MISMA CONEXION para trabajos de notarias
 * distintas, podria leer los expedientes de otra notaria. Ese es exactamente
 * el fallo que este diseno evita.
 *
 * Por eso `establecer()` EXIGE estar dentro de una transaccion y lanza
 * excepcion si no lo esta.
 */
class ContextoTenant
{
    /** Variable de sesion de PostgreSQL que lee la politica RLS. */
    public const VARIABLE = 'app.tenant_id';

    /**
     * Fija la notaria de la peticion en la conexion actual.
     *
     * @throws RuntimeException si no hay una transaccion abierta.
     */
    public function establecer(string $tenantId): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException(
                'No se puede fijar el contexto de notaria fuera de una transaccion. '
                . 'Use App\Services\Tenancy\ContextoTenant::ejecutarAislado().'
            );
        }

        DB::select('SELECT set_config(?, ?, true)', [self::VARIABLE, $tenantId]);
    }

    /**
     * Ejecuta una operacion dentro de una transaccion con la notaria fija.
     * Al salir, PostgreSQL descarta el valor de la variable.
     *
     * @template T
     *
     * @param  Closure(): T  $operacion
     * @return T
     */
    public function ejecutarAislado(string $tenantId, Closure $operacion): mixed
    {
        return DB::transaction(function () use ($tenantId, $operacion) {
            $this->establecer($tenantId);

            return $operacion();
        });
    }

    /**
     * Lee la variable de sesion sin pasar por `public.app_tenant_id()`, que
     * lanza excepcion cuando no hay contexto. Sirve para diagnostico.
     *
     * Si devuelve null, ninguna politica RLS de la base va a devolver filas.
     */
    public function tenantIdEnConexion(): ?string
    {
        $fila = DB::selectOne('SELECT current_setting(?, true) AS valor', [self::VARIABLE]);

        $valor = $fila->valor ?? null;

        return $valor === null || $valor === '' ? null : $valor;
    }
}