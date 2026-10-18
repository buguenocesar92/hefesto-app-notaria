<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente OIDC de Authentik.
 *
 * Este archivo NO implementa nada del flujo OIDC. Reutiliza Socialite, que ya
 * lo hace y ya esta probado. Lo que hay aca es lo especifico de esta
 * aplicacion:
 *
 *   - el estado de la peticion va FIRMADO y LIGADO A UNA NOTARIA, para que no
 *     se pueda cambiar a mano el destino del inicio de sesion;
 *   - el descubrimiento del proveedor se cachea.
 *
 * El `state` es lo que impide el ataque de login CSRF: sin el, un atacante
 * podria hacer que una persona de la notaria iniciara sesion con la cuenta de
 * el. Se firma con la APP_KEY y lleva dentro la notaria de destino.
 */
class ProveedorOidcAuthentik
{
    public const ESTADO = 'notaria.oauth.state';

    /**
     * Arma la URL a la que hay que redirigir al navegador.
     */
    public function urlDeAutorizacion(string $slugNotaria): string
    {
        $estado = $this->firmarEstado($slugNotaria);

        return $this->configuracion()['authorization_endpoint']
            . '?' . http_build_query([
                'client_id' => (string) config('authentik.client_id'),
                'redirect_uri' => (string) config('authentik.redirect_uri'),
                'response_type' => 'code',
                'scope' => (string) config('authentik.scopes'),
                'state' => $estado,
            ]);
    }

    /**
     * Descubre el proveedor. Se cachea porque no cambia.
     *
     * @return array<string, mixed>
     */
    public function configuracion(): array
    {
        $emisor = rtrim((string) config('authentik.issuer'), '/');

        return Cache::remember(
            'notaria.authentik.descubrimiento',
            (int) config('authentik.cache_segundos', 3600),
            function () use ($emisor): array {
                $respuesta = Http::timeout(10)
                    ->acceptJson()
                    ->get($emisor . '/.well-known/openid-configuration');

                if (! $respuesta->successful()) {
                    throw new RuntimeException(
                        'No se pudo leer la configuracion del proveedor de identidad en ' . $emisor
                    );
                }

                return $respuesta->json();
            }
        );
    }

    /**
     * Firma el estado: el destino no lo elige el navegador.
     */
    public function firmarEstado(string $slugNotaria): string
    {
        $contenido = json_encode([
            'notaria' => $slugNotaria,
            'emitido' => time(),
        ], JSON_THROW_ON_ERROR);

        $firma = hash_hmac('sha256', $contenido, (string) config('app.key'));

        return rtrim(strtr(base64_encode($contenido), '+/', '-_'), '=')
            . '.' . rtrim(strtr(base64_encode($firma), '+/', '-_'), '=');
    }

    /**
     * Verifica el estado recibido y devuelve la notaria de destino.
     */
    public function verificarEstado(string $estado): string
    {
        $partes = explode('.', $estado);

        if (count($partes) !== 2) {
            throw new RuntimeException('El estado del inicio de sesion no tiene el formato esperado.');
        }

        [$contenido, $firma] = $partes;

        $decodificado = base64_decode(strtr($contenido, '-_', '+/'), true);

        if ($decodificado === false) {
            throw new RuntimeException('El estado del inicio de sesion no se pudo leer.');
        }

        $esperada = hash_hmac('sha256', $decodificado, (string) config('app.key'));

        if (! hash_equals($esperada, base64_decode(strtr($firma, '-_', '+/'), true) ?: '')) {
            throw new RuntimeException('El estado del inicio de sesion no es valido.');
        }

        $datos = json_decode($decodificado, true, 512, JSON_THROW_ON_ERROR);

        // La ventana de 10 minutos evita que un estado robado ayer se reutilice
        // hoy.
        if (! isset($datos['emitido']) || (time() - (int) $datos['emitido']) > 600) {
            throw new RuntimeException('El estado del inicio de sesion esta vencido. Intente de nuevo.');
        }

        return (string) $datos['notaria'];
    }
}