<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoEtapa;
use App\Enums\EstadoExpediente;
use App\Http\Requests\Expedientes\AbrirExpedienteRequest;
use App\Http\Requests\Expedientes\CambiarEstadoEtapaRequest;
use App\Http\Resources\EtapaResource;
use App\Http\Resources\ExpedienteResource;
use App\Models\Etapa;
use App\Models\Expediente;
use App\Models\TipoOperacion;
use App\Services\Expedientes\AperturadorDeExpediente;
use App\Services\Expedientes\MaquinaDeEstadosEtapa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Expedientes: el centro del sistema.
 *
 * NO HAY RUTA PARA BORRAR UN EXPEDIENTE
 * ------------------------------------
 * Del mismo modo que no se borran los documentos, no se borra un
 * expediente. Un expediente mal abierto se ANULA, que deja rastro.
 */
class ExpedienteController extends Controller
{
    public function __construct(
        private readonly AperturadorDeExpediente $apertura,
        private readonly MaquinaDeEstadosEtapa $estados,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $expedientes = Expediente::query()
            ->with('tipoOperacion')
            ->when(
                $request->string('estado')->isNotEmpty(),
                fn ($consulta) => $consulta->where('estado', $request->string('estado')->toString())
            )
            ->when(
                $request->string('tipo_operacion')->isNotEmpty(),
                fn ($consulta) => $consulta->where('tipo_operacion_id', $request->string('tipo_operacion')->toString())
            )
            ->when(
                $request->string('buscar')->isNotEmpty(),
                fn ($consulta) => $consulta->where(function ($interna) use ($request): void {
                    $termino = '%' . $request->string('buscar')->toString() . '%';
                    $interna->where('folio', 'ilike', $termino)
                        ->orWhere('numero_protocolo', 'ilike', $termino)
                        ->orWhere('descripcion', 'ilike', $termino);
                })
            )
            ->orderByDesc('fecha_inicio')
            ->paginate($request->integer('por_pagina', 25));

        return ExpedienteResource::collection($expedientes)->response();
    }

    public function show(Expediente $expediente): JsonResponse
    {
        $this->authorize('view', $expediente);

        $expediente->load(['tipoOperacion', 'etapas.antecedentes', 'documentos.versiones']);

        return response()->json([
            'datos' => new ExpedienteResource($expediente),
            'nivel' => $expediente->peorNivel()->value,
        ]);
    }

    /**
     * Abre un expediente y copia sus etapas desde la plantilla.
     */
    public function store(AbrirExpedienteRequest $request): JsonResponse
    {
        $this->authorize('crear', Expediente::class);

        // `tipo_operacion_id` es un UUID: se lee del arreglo validado y NO
        // con `integer()`, que en un UUID devuelve 0.
        $validados = $request->validated();

        $tipoOperacion = TipoOperacion::query()->findOrFail($validados['tipo_operacion_id']);

        $expediente = $this->apertura->abrir(
            tenantId: $request->user()->tenant_id,
            tipoOperacion: $tipoOperacion,
            datos: $validados,
        );

        $expediente->load('etapas');

        return ExpedienteResource::make($expediente)
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Expediente $expediente): JsonResponse
    {
        $this->authorize('actualizar', $expediente);

        $validados = $request->validate([
            'descripcion' => ['nullable', 'string', 'max:250'],
            'detalle' => ['nullable', 'string', 'max:5000'],
            'numero_protocolo' => ['nullable', 'string', 'max:60'],
            'profesional_responsable_id' => ['nullable', 'uuid'],
        ]);

        $expediente->update($validados);

        return ExpedienteResource::make($expediente->refresh())->response();
    }

    /**
     * Cierra el expediente.
     *
     * Ojo con el nombre: cerrar NO declara valida la operacion. El sistema no
     * puede hacerlo, y por eso la accion se llama cerrar y no legalizar.
     */
    public function cerrar(Expediente $expediente): JsonResponse
    {
        $this->authorize('cerrar', $expediente);

        // Si quedan etapas esperando, no se cierra. El estado del expediente
        // se deriva de las etapas, y no hay forma de tener un expediente
        // "cerrado" con etapas abiertas: por eso el estado no se escribe
        // aca, se recalcula.
        $expediente = $this->estados->recalcularEstadoExpediente($expediente);

        if ($expediente->estado->value === 'en_tramite') {
            return response()->json([
                'mensaje' => 'El expediente no se puede cerrar: todavia tiene etapas sin terminar.',
                'expediente' => ExpedienteResource::make($expediente->load('etapas')),
            ], 409);
        }

        return response()->json([
            'mensaje' => 'Expediente cerrado por la notaria. '
                . 'Esto registra que la notaria dio el tramite por terminado; '
                . 'no declara que la operacion sea valida.',
            'expediente' => ExpedienteResource::make($expediente),
        ]);
    }

    /**
     * Anula un expediente mal abierto. No lo borra: queda el rastro.
     */
    public function anular(Expediente $expediente): JsonResponse
    {
        $this->authorize('anular', $expediente);

        $expediente->update([
            'estado' => EstadoExpediente::Anulado,
        ]);

        return response()->json([
            'mensaje' => 'Expediente anulado. El registro se conserva completo.',
        ]);
    }

    /**
     * Cambia el estado de una etapa y recalcula el estado del expediente.
     */
    public function cambiarEstadoEtapa(
        CambiarEstadoEtapaRequest $request,
        Expediente $expediente,
        Etapa $etapa,
    ): JsonResponse {
        $this->authorize('moverEtapa', $expediente);

        // La etapa TIENE que ser de este expediente. Sin esto, alguien podria
        // pasar el identificador de una etapa de otro expediente y moverla.
        // La RLS impide cruzar notarias, pero no impide cruzar expedientes
        // dentro de la misma.
        abort_unless($etapa->expediente_id === $expediente->id, 404);

        $etapa = $this->estados->cambiar(
            etapa: $etapa,
            nuevo: EstadoEtapa::from($request->string('estado')->toString()),
            comentario: $request->string('comentario')->toString() ?: null,
        );

        $expediente = $this->estados->recalcularEstadoExpediente($expediente);

        return response()->json([
            'etapa' => new EtapaResource($etapa->load('antecedentes')),
            'expediente' => new ExpedienteResource($expediente->refresh()->load('etapas')),
        ]);
    }

    /**
     * Cambia los umbrales del semaforo de una etapa concreta.
     *
     * Si se mandan nulos, la etapa queda SIN umbral y el semaforo dejara de
     * marcarla. Es una accion valida y a veces necesaria: una etapa que se
     * sabe larga no deberia estar en rojo desde el primer dia.
     */
    public function cambiarUmbrales(Request $request, Expediente $expediente, Etapa $etapa): JsonResponse
    {
        $this->authorize('actualizar', $expediente);

        abort_unless($etapa->expediente_id === $expediente->id, 404);

        $validados = $request->validate([
            'dias_alerta' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'dias_critico' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        if (
            ($validados['dias_alerta'] ?? null) !== null
            && ($validados['dias_critico'] ?? null) !== null
            && $validados['dias_critico'] < $validados['dias_alerta']
        ) {
            return response()->json([
                'mensaje' => 'El umbral critico no puede ser menor que el de alerta.',
                'errores' => ['dias_critico' => ['Debe ser mayor o igual que dias_alerta.']],
            ], 422);
        }

        $etapa->update($validados);

        return response()->json([
            'etapa' => new EtapaResource($etapa->refresh()->load('antecedentes')),
            'nota' => 'Estos son umbrales internos de la notaria. No son plazos legales.',
        ]);
    }
}