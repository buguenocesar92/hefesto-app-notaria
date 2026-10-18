<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\AuditorDeAcceso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anota en la bitacora las peticiones que escriben datos.
 *
 * Se ejecuta DESPUES de la respuesta, con try/finally, para que tambien
 * queden registradas las peticiones que fallaron. Nunca deja que un fallo de
 * la bitacora tumbe la peticion.
 */
class AuditarAcceso
{
    public function __construct(private readonly AuditorDeAcceso $auditor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $codigoEstado = 500;

        try {
            /** @var Response $respuesta */
            $respuesta = $next($request);
            $codigoEstado = $respuesta->getStatusCode();

            return $respuesta;
        } finally {
            $this->auditor->registrarPeticion($request, $codigoEstado);
        }
    }
}