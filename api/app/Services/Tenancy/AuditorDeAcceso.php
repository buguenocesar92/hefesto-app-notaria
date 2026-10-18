<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\BitacoraAcceso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Escribe la bitacora de acceso.
 *
 * POR QUE HAY UNA BITACORA, Y NO ES POR CUMPLIR
 * ----------------------------------------------
 * En una notaria, los datos de las partes y de las operaciones sobre
 * patrimonio, familia y sucesiones son datos personales sensibles por la
 * naturaleza misma del tramite, no por una etiqueta. La pregunta "quien abrio
 * este expediente y cuando" se le hace a una notaria cuando un tercero
 * reclama. La bitacora es la respuesta.
 *
 * Ademas cumple con lo que exige la Ley 19.628 en materia de seguridad:
 * un registro de las operaciones realizadas sobre los datos personales.
 * Ver docs/07.
 *
 * LOS HECHOS POSTERIORES AL LOGIN van a la tabla `bitacora_accesos`. Los
 * anteriores al login (estado invalido, token expirado, intento contra una
 * notaria desconocida) NO pueden ir ahi, porque no tienen notaria y la
 * politica RLS lo prohibiria: van al canal de log `notaria_seguridad`.
 */
class AuditorDeAcceso
{
    public function __construct(private readonly ContextoTenant $contexto) {}

    /** Registra el desenlace de una peticion autenticada. */
    public function registrarPeticion(Request $request, int $codigoEstado): void
    {
        $usuario = auth()->user();

        if ($usuario === null) {
            return;
        }

        $accion = $this->accionDe($request);

        // Si no hay nada que anotar, no se abre una transaccion.
        if ($accion === null) {
            return;
        }

        try {
            $this->contexto->ejecutarAislado($usuario->tenant_id, function () use ($request, $usuario, $accion, $codigoEstado): void {
                BitacoraAcceso::create([
                    'tenant_id' => $usuario->tenant_id,
                    'usuario_id' => $usuario->id,
                    'expediente_id' => $this->expedienteDeLaRuta($request),
                    'accion' => $accion,
                    'entidad' => $request->route()?->getName(),
                    'entidad_id' => null,
                    'metodo_http' => $request->method(),
                    'ruta' => mb_substr($request->path(), 0, 255),
                    'direccion_ip' => $request->ip(),
                    'agente_usuario' => mb_substr((string) $request->userAgent(), 0, 255),
                    'exito' => $codigoEstado < 400,
                    'registrado_en' => now(),
                ]);
            });
        } catch (\Throwable $excepcion) {
            // La bitacora NUNCA debe tumbar la peticion. Si falla, se avisa
            // por log y la respuesta sigue su curso.
            Log::channel('notaria_seguridad')->error('No se pudo escribir la bitacora de acceso', [
                'accion' => $accion,
                'error' => $excepcion->getMessage(),
            ]);
        }
    }

    /** Hechos previos al login: van al log, porque no hay notaria todavia. */
    public function registrarEventoDeAcceso(string $evento, array $contexto = []): void
    {
        Log::channel('notaria_seguridad')->warning('Acceso al sistema: ' . $evento, $contexto);
    }

    /**
     * Que acciones se anotan.
     *
     * Solo las que ESCRIBEN. Ver un expediente no se anota en la bitacora de
     * escritura: en una notaria todas las personas de la notaria ven los
     * expedientes, y anotar cada lectura llenaría la tabla de ruido y la
     * haria inutil. Si lo que se necesita es trazarlecturas, hay que decirlo
     * explicitamente en docs/07, no deducirlo de un filtro de metodo HTTP.
     */
    private function accionDe(Request $request): ?string
    {
        if ($request->isMethod('get')) {
            return null;
        }

        $ruta = $request->path();

        return match (true) {
            $request->isMethod('post') && str_contains($ruta, '/estado') => 'cambiar_estado',
            $request->isMethod('post') && str_contains($ruta, '/antecedentes') => 'registrar_antecedente',
            $request->isMethod('post') && str_contains($ruta, '/versiones') => 'agregar_version_documento',
            $request->isMethod('post') && str_contains($ruta, '/reemplazar') => 'marcar_reemplazado',
            $request->isMethod('post') && str_contains($ruta, '/cobranzas') => 'registrar_cobro',
            $request->isMethod('post') && str_contains($ruta, '/configuracion') => 'actualizar_configuracion',
            $request->isMethod('post') => 'crear',
            $request->isMethod('patch'), $request->isMethod('put') => 'actualizar',
            default => null,
        };
    }

    /** El expediente de la ruta, si la peticion va sobre uno. */
    private function expedienteDeLaRuta(Request $request): ?string
    {
        $expediente = $request->route('expediente');

        return $expediente instanceof \App\Models\Expediente ? $expediente->id : null;
    }
}