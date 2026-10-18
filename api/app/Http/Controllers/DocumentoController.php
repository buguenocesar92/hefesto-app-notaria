<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoDocumento;
use App\Http\Resources\DocumentoResource;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\Expediente;
use App\Services\Documentos\GestorDeDocumentos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Documentos del expediente.
 *
 * LA REGLA DE ESTE ARCHIVO
 * -----------------------
 * NO HAY RUTA DELETE. Ni por documento, ni por version. No hay `destroy()`, no
 * hay `delete()`, y el servicio `GestorDeDocumentos` no expone ningun metodo
 * que borre.
 *
 * No es una omision: es la regla del sistema. Un expediente notarial sin su
 * historial documental no vale nada, y una aplicacion que permite borrar un
 * documento es una aplicacion que puede dejar un expediente invalido sin
 * avisar.
 *
 * Lo que si hay:
 *   - subir la primera version
 *   - agregar una version nueva (la anterior queda, con fecha de no vigencia)
 *   - marcar el documento como reemplazado (el registro queda)
 */
class DocumentoController extends Controller
{
    public function __construct(private readonly GestorDeDocumentos $gestor) {}

    public function index(Expediente $expediente): JsonResponse
    {
        $this->authorize('view', $expediente);

        $documentos = $expediente->documentos()
            ->with('versiones')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            // Se manda el historial COMPLETO, sin filtrar los reemplazados.
            // Si la interfaz quisiera mostrar solo los vigentes, ese filtro
            // lo hace la interfaz; la API no lo hace por ella.
            'datos' => DocumentoResource::collection($documentos),
            'vigentes' => $documentos->where('estado', EstadoDocumento::Vigente)->count(),
            'reemplazados' => $documentos->where('estado', EstadoDocumento::Reemplazado)->count(),
            'aviso' => 'Un documento de un expediente no se borra: se reemplaza. '
                . 'El historial queda completo.',
        ]);
    }

    /**
     * Sube la primera version de un documento.
     */
    public function store(Request $request, Expediente $expediente): JsonResponse
    {
        $this->authorize('crear', Expediente::class);

        $validados = $request->validate([
            'archivo' => ['required', 'file', 'max:51200'],
            'nombre' => ['required', 'string', 'max:200'],
            'tipo' => ['nullable', 'string', 'max:40'],
            'fecha_documento' => ['nullable', 'date_format:Y-m-d'],
        ], [], ['archivo' => 'archivo', 'nombre' => 'nombre']);

        $documento = $this->gestor->crear(
            tenantId: $request->user()->tenant_id,
            expedienteId: $expediente->id,
            archivo: $validados['archivo'],
            nombre: $validados['nombre'],
            tipo: $validados['tipo'] ?? null,
            fechaDocumento: $validados['fecha_documento'] ?? null,
            usuarioId: $request->user()->id,
        );

        return DocumentoResource::make($documento->load('versiones'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Agrega una version nueva. La anterior NO se borra.
     */
    public function agregarVersion(Request $request, Documento $documento): JsonResponse
    {
        $this->authorize('agregarVersion', $documento);

        $validados = $request->validate([
            'archivo' => ['required', 'file', 'max:51200'],
            'fecha_documento' => ['nullable', 'date_format:Y-m-d'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ], [], ['archivo' => 'archivo']);

        $version = $this->gestor->agregarVersion(
            documento: $documento,
            archivo: $validados['archivo'],
            usuarioId: $request->user()->id,
            fechaDocumento: $validados['fecha_documento'] ?? null,
            observacion: $validados['observacion'] ?? null,
        );

        return response()->json([
            'mensaje' => 'Version agregada. La version anterior se conserva: '
                . 'dejo de estar vigente, pero no se borro.',
            'version' => $version,
            'documento' => new DocumentoResource($documento->refresh()->load('versiones')),
        ], 201);
    }

    /**
     * Marca un documento como reemplazado por otro.
     *
     * El documento queda, con la fecha y con quien lo hizo.
     */
    public function marcarReemplazado(Request $request, Documento $documento): JsonResponse
    {
        $this->authorize('marcarReemplazado', $documento);

        $validados = $request->validate([
            'reemplazado_por_documento_id' => ['nullable', 'uuid'],
            'motivo' => ['nullable', 'string', 'max:250'],
        ]);

        $documento = $this->gestor->marcarReemplazado(
            documento: $documento,
            usuarioId: $request->user()->id,
            reemplazadoPorDocumentoId: $validados['reemplazado_por_documento_id'] ?? null,
            motivo: $validados['motivo'] ?? null,
        );

        return response()->json([
            'mensaje' => 'Documento marcado como reemplazado. El registro se conserva '
                . 'completo, con la fecha y con quien lo hizo.',
            'documento' => new DocumentoResource($documento->load('versiones')),
        ]);
    }

    /**
     * Descarga una version concreta.
     *
     * Autoriza ANTES de tocar el disco, y despues resuelve la ruta
     * comprobando que no se sale del volumen: la RLS protege el dato, pero no
     * el archivo.
     */
    public function descargar(Request $request, Documento $documento, DocumentoVersion $version): BinaryFileResponse|Response
    {
        $this->authorize('descargar', $documento);

        // La version TIENE que ser de este documento. Mismo motivo que en las
        // etapas: la RLS no impide cruzar recursos dentro de la misma
        // notaria.
        abort_unless($version->documento_id === $documento->id, 404);

        $ruta = $this->gestor->rutaAbsolutaSegura($version);

        return response()->download($ruta, $version->nombre_archivo, [
            'Content-Type' => $version->mime ?? 'application/octet-stream',
        ]);
    }
}