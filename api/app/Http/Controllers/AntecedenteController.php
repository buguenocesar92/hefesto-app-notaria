<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoAntecedente;
use App\Models\Antecedente;
use App\Models\Expediente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Antecedentes: lo que hay que conseguir.
 *
 * Es el modulo que ataca el problema real del negocio: el expediente que se
 * queda parado porque nadie fue a buscar algo.
 *
 * Registrar el antecedente por separado deja constancia de a quien se le
 * pidio y cuando. Si el cliente pregunta "en que anda mi tramite", la
 * respuesta no es "esta en proceso": es "se pidio el X a tal persona el dia Y,
 * y no ha llegado".
 */
class AntecedenteController extends Controller
{
    public function index(Expediente $expediente): JsonResponse
    {
        $this->authorize('view', $expediente);

        $antecedentes = $expediente->antecedentes()->orderByDesc('solicitado_en')->get();

        return response()->json([
            'datos' => $antecedentes,
            'pendientes' => $antecedentes->where('estado', EstadoAntecedente::Pendiente)->count(),
        ]);
    }

    public function store(Request $request, Expediente $expediente): JsonResponse
    {
        $this->authorize('moverEtapa', $expediente);

        $validados = $request->validate([
            'descripcion' => ['required', 'string', 'max:250'],
            'etapa_id' => ['nullable', 'uuid'],
            'solicitado_a' => ['nullable', 'string', 'max:200'],
            'responsable_id' => ['nullable', 'uuid'],
            'solicitado_en' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $antecedente = Antecedente::create([
            'tenant_id' => $request->user()->tenant_id,
            'expediente_id' => $expediente->id,
            'descripcion' => $validados['descripcion'],
            'etapa_id' => $validados['etapa_id'] ?? null,
            'solicitado_a' => $validados['solicitado_a'] ?? null,
            'responsable_id' => $validados['responsable_id'] ?? $request->user()->id,
            'solicitado_en' => $validados['solicitado_en'] ?? now()->toDateString(),
            'estado' => EstadoAntecedente::Pendiente,
        ]);

        return response()->json($antecedente, 201);
    }

    /**
     * Registra que el antecedente llego (o que no llego, y por que).
     */
    public function update(Request $request, Antecedente $antecedente): JsonResponse
    {
        $this->authorize('moverEtapa', $antecedente->expediente);

        $validados = $request->validate([
            'estado' => ['required', 'string', 'in:' . implode(',', EstadoAntecedente::opciones())],
            'respuesta' => ['nullable', 'string', 'max:2000'],
            'recibido_en' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $antecedente->update([
            'estado' => $validados['estado'],
            'respuesta' => $validados['respuesta'] ?? $antecedente->respuesta,
            // La fecha de recepcion solo tiene sentido si llego.
            'recibido_en' => $validados['estado'] === EstadoAntecedente::Recibido->value
                ? ($validados['recibido_en'] ?? now()->toDateString())
                : $antecedente->recibido_en,
        ]);

        return response()->json($antecedente->refresh());
    }
}