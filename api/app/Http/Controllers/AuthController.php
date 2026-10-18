<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notaria;
use App\Models\Usuario;
use App\Services\Auth\ProveedorOidcAuthentik;
use App\Services\Auth\VerificadorIdToken;
use App\Services\Tenancy\AuditorDeAcceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Autenticacion contra Authentik (OpenID Connect).
 *
 * La contrasena nunca pasa por esta aplicacion. Authentik autentica, esta
 * aplicacion recibe un token de identificacion, lo verifica y abre sesion.
 *
 * ANTES DEL LOGIN, SIN NOTARIA
 * ----------------------------
 * Las peticiones de esta pantalla son las unicas que corren sin contexto de
 * tenant. Por eso no pueden tocar ninguna tabla de negocio: si lo hicieran,
 * `public.app_tenant_id()` lanzaria excepcion. Para ubicar la notaria se lee
 * la vista `vw_notarias_publicas`, que expone SOLO slug, nombre y activo, y
 * solo de las notarias activas.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly ProveedorOidcAuthentik $proveedor,
        private readonly VerificadorIdToken $verificador,
        private readonly AuditorDeAcceso $auditor,
    ) {}

    /**
     * Las notarias activas, para la pantalla de entrada.
     *
     * Se lee la VISTA, no la tabla: son tres columnas a proposito.
     */
    public function notarias(): JsonResponse
    {
        return response()->json([
            'notarias' => DB::table('vw_notarias_publicas')
                ->orderBy('nombre')
                ->get(['slug', 'nombre']),
        ]);
    }

    /** Redirige al proveedor de identidad. */
    public function entrar(Request $request, string $slug): JsonResponse
    {
        $existe = DB::table('vw_notarias_publicas')
            ->where('slug', $slug)
            ->first();

        if ($existe === null) {
            $this->auditor->registrarEventoDeAcceso('entra a una notaria que no existe o esta inactiva', [
                'slug' => $slug,
            ]);

            return response()->json([
                'mensaje' => 'No hay ninguna notaria activa con ese identificador.',
            ], 404);
        }

        return response()->json([
            'redirigir_a' => $this->proveedor->urlDeAutorizacion($slug),
        ]);
    }

    /**
     * Callback del proveedor: verifica el token y abre sesion.
     */
    public function callback(Request $request): JsonResponse
    {
        try {
            $identidad = $this->verificador->verificar($request->query('state'), $request->query('code'));

            $usuario = $this->resolverUsuario($identidad);
        } catch (\Throwable $excepcion) {
            $this->auditor->registrarEventoDeAcceso('intento de inicio de sesion rechazado', [
                'error' => $excepcion->getMessage(),
            ]);

            return response()->json([
                'mensaje' => 'No se pudo iniciar sesion.',
            ], 401);
        }

        Auth::guard('web')->login($usuario, true);

        $usuario->forceFill(['ultimo_acceso' => now()])->save();

        return response()->json([
            'redirigir_a' => '/',
            'usuario' => [
                'nombre' => $usuario->nombreCompleto(),
                'rol' => $usuario->rol->value,
                'rol_etiqueta' => $usuario->rol->etiqueta(),
                'notaria' => $usuario->tenant_id,
            ],
        ]);
    }

    public function salir(): JsonResponse
    {
        Auth::guard('web')->logout();

        return response()->json(['mensaje' => 'Sesion cerrada.']);
    }

    /**
     * Busca al usuario en la base por su identidad de Authentik.
     *
     * Si la identidad es correcta pero la persona no esta dada de alta en la
     * notaria, NO se crea sola: una cuenta se da de alta a mano, con un rol.
     * Autoaprovisionar cuentas convertiria cualquier cuenta del proveedor de
     * identidad en acceso a expedientes notariales.
     *
     * @param  array{sub: string, email: ?string, name: ?string}  $identidad
     */
    private function resolverUsuario(array $identidad): Usuario
    {
        $usuario = Usuario::query()->where('authentik_sub', $identidad['sub'])->first();

        if ($usuario === null) {
            throw new \RuntimeException(
                'La identidad es valida pero no hay ninguna cuenta dada de alta con ese identificador.'
            );
        }

        if (! $usuario->activo) {
            throw new \RuntimeException('La cuenta esta desactivada.');
        }

        return $usuario;
    }
}