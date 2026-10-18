<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoCita;
use App\Models\AgendaCita;
use App\Models\Profesional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Agenda por profesional, con duracion y bloqueos.
 *
 * No decide si algo es valido: solo reserva tiempo. Por eso no hay ninguna
 * comprobacion de plazos aca.
 */
class AgendaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AgendaCita::class);

        $citas = AgendaCita::query()
            ->with(['profesional', 'expediente', 'tipoOperacion'])
            ->when(
                $request->date('desde')->isValid(),
                fn ($consulta) => $consulta->where('fin', '>=', $request->date('desde')->toDateTimeString())
            )
            ->when(
                $request->date('hasta')->isValid(),
                fn ($consulta) => $consulta->where('inicio', '<=', $request->date('hasta')->endOfDay()->toDateTimeString())
            )
            ->when(
                $request->string('profesional_id')->isNotEmpty(),
                fn ($consulta) => $consulta->where('profesional_id', $request->string('profesional_id')->toString())
            )
            ->orderBy('inicio')
            ->get();

        return response()->json([
            'datos' => $citas,
            'bloqueos' => $citas->filter(static fn (AgendaCita $cita): bool => $cita->esBloqueo())->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('crear', AgendaCita::class);

        $validados = $request->validate([
            'profesional_id' => ['required', 'uuid'],
            'expediente_id' => ['nullable', 'uuid'],
            'tipo_operacion_id' => ['nullable', 'uuid'],
            'titulo' => ['required', 'string', 'max:160'],
            'estado' => ['nullable', 'string', 'in:' . implode(',', EstadoCita::opciones())],
            'inicio' => ['required', 'date'],
            'fin' => ['required', 'date', 'after:inicio'],
            'bloqueo' => ['nullable', 'string', 'max:40'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ], [], ['inicio' => 'inicio', 'fin' => 'fin']);

        // La duracion se propone desde el tipo de operacion o el profesional
        // si no viene. Es una ayuda para agendar, no un plazo.
        $duracion = (int) round(
            strtotime($validados['fin']) - strtotime($validados['inicio'])
        ) / 60;

        $cita = AgendaCita::create([
            'tenant_id' => $request->user()->tenant_id,
            'profesional_id' => $validados['profesional_id'],
            'expediente_id' => $validados['expediente_id'] ?? null,
            'tipo_operacion_id' => $validados['tipo_operacion_id'] ?? null,
            'titulo' => $validados['titulo'],
            'estado' => $validados['estado'] ?? EstadoCita::Confirmada->value,
            'inicio' => $validados['inicio'],
            'fin' => $validados['fin'],
            'duracion_minutos' => $duracion,
            'bloqueo' => $validados['bloqueo'] ?? null,
            'notas' => $validados['notas'] ?? null,
            'creado_por' => $request->user()->id,
        ]);

        return response()->json($cita->load('profesional'), 201);
    }

    public function update(Request $request, AgendaCita $cita): JsonResponse
    {
        $this->authorize('actualizar', $cita);

        $validados = $request->validate([
            'titulo' => ['sometimes', 'string', 'max:160'],
            'estado' => ['sometimes', 'string', 'in:' . implode(',', EstadoCita::opciones())],
            'inicio' => ['sometimes', 'date'],
            'fin' => ['sometimes', 'date'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);

        $cita->update($validados);

        return response()->json($cita->refresh());
    }

    /**
     * Cancela una cita. No la borra: una cita cancelada queda en la agenda
     * marcada como cancelada, para que se vea que existio.
     */
    public function cancelar(AgendaCita $cita): JsonResponse
    {
        $this->authorize('cancelar', $cita);

        $cita->update(['estado' => EstadoCita::Cancelada]);

        return response()->json([
            'mensaje' => 'Cita cancelada. El registro queda: no se borran las citas.',
            'cita' => $cita->refresh(),
        ]);
    }

    /** Los profesionales con agenda habilitada. */
    public function profesionales(): JsonResponse
    {
        return response()->json(Profesional::query()->activos()->orderBy('nombre')->get());
    }
}