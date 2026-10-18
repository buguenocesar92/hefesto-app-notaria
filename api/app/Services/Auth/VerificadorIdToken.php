<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Verifica el token de identificacion que devuelve Authentik.
 *
 * QUE SE COMPRUEBA
 * ----------------
 * 1. Que la FIRMA del token sea valida, contra las claves publicas del
 *    proveedor. Si la firma no es valida, el token lo pudo fabricar cualquiera
 *    que se haga pasar por el proveedor.
 * 2. Que el EMISOR sea el esperado, para que un token de otro proveedor no
 *    sea aceptado.
 * 3. Que la AUDIENCIA sea esta aplicacion, para que un token emitido para
 *    otro cliente no sirva aca.
 * 4. Que no este VENCIDO, con una tolerancia pequena por desfase de reloj.
 *
 * LO QUE NO SE COMPRUEBA
 * ----------------------
 * Que la persona pueda o no pueda entrar a la notaria. Eso lo decide el rol
 * guardado en la base, no el proveedor de identidad. Ver `AuthController`.
 */
class VerificadorIdToken
{
    public function __construct(private readonly ProveedorOidcAuthentik $proveedor) {}

    /**
     * @return array{sub: string, email: ?string, name: ?string}
     */
    public function verificar(?string $estado, ?string $codigo): array
    {
        if ($estado === null || $codigo === null) {
            throw new RuntimeException('Faltan parametros en el callback del proveedor de identidad.');
        }

        // El estado se verifica PRIMERO: si el estado no es valido, no se pide
        // nada al proveedor.
        $slugNotaria = $this->proveedor->verificarEstado($estado);

        $token = $this->pedirToken($codigo);

        $afirmaciones = $this->verificarFirma($token);

        $this->revisarEmisor($afirmaciones);
        $this->revisarAudiencia($afirmaciones);
        $this->revisarVencimiento($afirmaciones);

        return [
            'sub' => (string) $afirmaciones['sub'],
            'email' => isset($afirmaciones['email']) ? (string) $afirmaciones['email'] : null,
            'name' => isset($afirmaciones['name']) ? (string) $afirmaciones['name'] : null,
        ];
    }

    private function pedirToken(string $codigo): array
    {
        $respuesta = Http::asForm()->timeout(10)->post(
            $this->proveedor->configuracion()['token_endpoint'],
            [
                'grant_type' => 'authorization_code',
                'client_id' => (string) config('authentik.client_id'),
                'client_secret' => (string) config('authentik.client_secret'),
                'redirect_uri' => (string) config('authentik.redirect_uri'),
                'code' => $codigo,
            ]
        );

        if (! $respuesta->successful() || ! isset($respuesta->json()['id_token'])) {
            throw new RuntimeException('El proveedor de identidad no devolvio un token de identificacion.');
        }

        return $respuesta->json();
    }

    /**
     * @param  array<string, mixed>  $respuesta
     * @return array<string, mixed>
     */
    private function verificarFirma(array $respuesta): array
    {
        $afirmaciones = (new \Firebase\JWT\JWT())
            ->decode(
                $respuesta['id_token'],
                $this->clavePublica(),
            );

        return (array) $afirmaciones;
    }

    /**
     * Trae el juego de claves publicas del proveedor y se lo entrega a
     * firebase/php-jwt, que escoge la correcta segun el `kid` del token.
     */
    private function clavePublica(): \Firebase\JWT\Key
    {
        $emisor = rtrim((string) config('authentik.issuer'), '/');

        $respuesta = Http::timeout(10)->get($emisor . '/application/j/' . config('authentik.client_id') . '/jwks/');

        if (! $respuesta->successful()) {
            throw new RuntimeException('No se pudieron obtener las claves publicas del proveedor de identidad.');
        }

        return \Firebase\JWT\JWK::parseKeySet(
            $respuesta->json(),
            (string) config('authentik.algoritmo')
        );
    }

    /** @param array<string, mixed> $afirmaciones */
    private function revisarEmisor(array $afirmaciones): void
    {
        $esperado = rtrim((string) config('authentik.issuer'), '/');

        if (rtrim((string) ($afirmaciones['iss'] ?? ''), '/') !== $esperado) {
            throw new RuntimeException('El token de identificacion viene de otro emisor.');
        }
    }

    /** @param array<string, mixed> $afirmaciones */
    private function revisarAudiencia(array $afirmaciones): void
    {
        $esperado = (string) config('authentik.client_id');
        $audiencia = $afirmaciones['aud'] ?? null;

        $coincide = is_array($audiencia)
            ? in_array($esperado, $audiencia, true)
            : $audiencia === $esperado;

        if (! $coincide) {
            throw new RuntimeException('El token de identificacion no es para esta aplicacion.');
        }
    }

    /** @param array<string, mixed> $afirmaciones */
    private function revisarVencimiento(array $afirmaciones): void
    {
        $tolerancia = (int) config('authentik.tolerancia_minutos', 2) * 60;
        $expira = (int) ($afirmaciones['exp'] ?? 0);

        if ($expira === 0 || $expira < (time() - $tolerancia)) {
            throw new RuntimeException('El token de identificacion esta vencido.');
        }
    }
}