<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Models\Parte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Partes: las personas de las operaciones.
 *
 * El ROL NO SE ESCRIBE ACA. Se escribe en `expediente_partes`, que es donde
 * vive la relacion entre la persona y el expediente.
 *
 * Ver `Parte`: el rol es de la relacion, no de la persona. La misma persona
 * puede ser compradora en un expediente y testigo en otro, y el sistema tiene
 * que poder decirlo sin duplicarla.
 */
class ParteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Parte::class);

        $partes = Parte::query()
            ->when(
                $request->string('buscar')->isNotEmpty(),
                fn ($consulta) => $consulta->where('nombre', 'ilike', '%' . $request->string('buscar')->toString() . '%')
            )
            ->orderBy('nombre')
            ->paginate($request->integer('por_pagina', 25));

        return response()->json($partes);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('crear', Parte::class);

        $validados = $this->validar($request);

        $parte = Parte::create([
            'tenant_id' => $request->user()->tenant_id,
            ...$validados,
        ]);

        return response()->json($parte, 201);
    }

    public function update(Request $request, Parte $parte): JsonResponse
    {
        $this->authorize('actualizar', $parte);

        $parte->update($this->validar($request));

        return response()->json($parte->refresh());
    }

    /**
     * Asigna un rol a esta parte EN UN EXPEDIENTE.
     *
     * El rol es texto libre y lo define la notaria. El sistema trae algunos
     * sugeridos en la interfaz, pero NO restringe la lista: la lista de roles
     * que una operacion reconoce depende de la operacion y del marco
     * aplicable, y este sistema no conoce ese marco.
     */
    public function asignarRol(Request $request, Parte $parte): JsonResponse
    {
        $this->authorize('crear', Expediente::class);

        $validados = $request->validate([
            'expediente_id' => ['required', 'uuid'],
            'rol' => ['required', 'string', 'max:40'],
            'es_principal' => ['nullable', 'boolean'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $parte->expedientes()->syncWithoutDetaching([
            $validados['expediente_id'] => [
                'id' => (string) Str::uuid(),
                'tenant_id' => $parte->tenant_id,
                'rol' => $validados['rol'],
                'es_principal' => $validados['es_principal'] ?? false,
                'observacion' => $validados['observacion'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        return response()->json([
            'mensaje' => 'Rol asignado en esa operacion.',
            'expedientes' => $parte->expedientes()->get(['expedientes.id', 'expedientes.folio'])->map(
                fn ($expediente): array => [
                    'id' => $expediente->id,
                    'folio' => $expediente->folio,
                    'rol' => $expediente->pivot->rol,
                ]
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:160'],
            'apellidos' => ['nullable', 'string', 'max:160'],
            'rut' => ['nullable', 'string', 'max:20'],
            'correo' => ['nullable', 'email', 'max:190'],
            'telefono' => ['nullable', 'string', 'max:32'],
            'direccion' => ['nullable', 'string', 'max:250'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}