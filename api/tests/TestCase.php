<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Notaria;
use App\Models\Profesional;
use App\Models\TipoOperacion;
use App\Models\Usuario;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

/**
 * Base de las pruebas.
 *
 * Aporta utilidades para el aislamiento, que es lo que mas se prueba:
 *
 *  - `comoUsuario()`, que autentica a una persona y fija su contexto de
 *    notaria en la base. IMPORTANTE: autenticar con `actingAs()` NO fija el
 *    contexto en la base de datos, porque el middleware `ResolverTenant` corre
 *    dentro del grupo `web`, que las pruebas no ejecutan. Por eso ademas de
 *    autenticar, las pruebas fijan el contexto: si no, la RLS no tendria
 *    contra que filtrar.
 *
 *  - `crearNotaria()`, que arma una notaria con su administrador.
 */
abstract class TestCase extends BaseTestCase
{
    /** Notaria fijada para la prueba en curso. */
    protected ?string $tenantDePrueba = null;

    /**
     * Autentica a un usuario y fija su contexto de notaria en la base.
     */
    protected function comoUsuario(Usuario $usuario): static
    {
        $this->actingAs($usuario);

        return $this->fijarTenant($usuario->tenant_id);
    }

    /**
     * Emula lo que hace `App\Http\Middleware\ResolverTenant`.
     *
     * Se usa `set_config(..., false)`, o sea con alcance de SESION y no de
     * transaccion. En la aplicacion real es al reves (alcance de transaccion,
     * para que el worker de colas no reutilice la notaria de otra). En las
     * pruebas cada caso abre su propia aplicacion y su propia conexion, asi
     * que el valor no sobrevive entre pruebas: por eso el alcance de sesion es
     * seguro aca y hace falta para que las aserciones (que corren fuera de la
     * transaccion del middleware) sigan viendo el mismo contexto.
     */
    protected function fijarTenant(?string $tenantId): static
    {
        if ($tenantId === null) {
            return $this;
        }

        DB::select('SELECT set_config(?, ?, false)', [ContextoTenant::VARIABLE, $tenantId]);

        $this->tenantDePrueba = $tenantId;

        return $this;
    }

    /**
     * Crea una notaria con su administrador y su profesional, y fija el
     * contexto.
     *
     * @return array{notaria: Notaria, administrador: Usuario, notario: Usuario, profesional: Profesional, tipo: TipoOperacion}
     */
    protected function crearNotaria(string $dominio = 'notaria-ejemplo.cl'): array
    {
        $notaria = Notaria::factory()->create([
            'slug' => 'notaria-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)),
            'nombre' => 'Notaria de prueba',
        ]);

        $this->fijarTenant($notaria->id);

        $administrador = Usuario::factory()->administrador()->de($notaria)->create([
            'correo' => 'admin@' . $dominio,
        ]);

        $notario = Usuario::factory()->notario()->de($notaria)->create([
            'correo' => 'notario@' . $dominio,
        ]);

        $profesional = Profesional::factory()->create([
            'tenant_id' => $notaria->id,
            'usuario_id' => $notario->id,
            'nombre' => 'Notario',
            'apellidos' => 'De Prueba',
        ]);

        $tipo = TipoOperacion::factory()->create([
            'tenant_id' => $notaria->id,
            'codigo' => 'PRUEBA',
            'nombre' => 'Tipo de prueba',
        ]);

        return [
            'notaria' => $notaria,
            'administrador' => $administrador,
            'notario' => $notario,
            'profesional' => $profesional,
            'tipo' => $tipo,
        ];
    }

    /**
     * Crea una SEGUNDA notaria, independiente de la primera, y deja su
     * contexto fijado. Util para comprobar que no se mezclan los datos.
     *
     * @return array{notaria: Notaria, administrador: Usuario, notario: Usuario, profesional: Profesional, tipo: TipoOperacion}
     */
    protected function crearSegundaNotaria(string $dominio = 'otra-notaria.cl'): array
    {
        return $this->crearNotaria($dominio);
    }
}