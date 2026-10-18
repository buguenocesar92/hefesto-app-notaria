<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoCobranza;
use App\Models\Cobranza;
use App\Models\Expediente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cobranzas: el REGISTRO de lo facturado.
 *
 * LO QUE ESTE CONTROLADOR NO HACE, Y NO VA A HACER
 * -------------------------------------------------
 * No emite documentos tributarios electronicos. No los timbra, no los anula, no
 * los envia al cliente, no los manda al acceptedor.
 *
 * Hefesto tiene el kit LibreDTE, pero su version publicada es una beta y su
 * API es de SOLO LECTURA: no permite automatizar la emision. Por eso aqui no
 * hay, y no habra, codigo que lo haga.
 *
 * Lo que hay es un registro: la notaria escribe aqui lo que YA emitio por su
 * cuenta, con su numero real. Es un respaldo contable, no un emisor. Ver
 * docs/05, seccion "Facturacion electronica: lo que no se hace".
 */
class CobranzaController extends Controller
{
    public function index(Expediente $expediente): JsonResponse
    {
        $this->authorize('view', $expediente);

        return response()->json([
            'datos' => $expediente->cobranzas()->get(),
            'total' => (float) $expediente->cobranzas()->where('estado', '!=', EstadoCobranza::Anulada->value)->sum('monto'),
            'moneda' => 'CLP',
        ]);
    }

    public function store(Request $request, Expediente $expediente): JsonResponse
    {
        $this->authorize('facturar', $expediente);

        $validados = $request->validate([
            'concepto' => ['required', 'string', 'max:200'],
            'monto' => ['required', 'numeric', 'min:0'],
            'fecha_emision' => ['required', 'date_format:Y-m-d'],
            'parte_id' => ['nullable', 'uuid'],
            'referencia_documento_tributario' => ['nullable', 'string', 'max:60'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        $cobranza = Cobranza::create([
            'tenant_id' => $request->user()->tenant_id,
            'expediente_id' => $expediente->id,
            'parte_id' => $validados['parte_id'] ?? null,
            'concepto' => $validados['concepto'],
            'monto' => $validados['monto'],
            'moneda' => 'CLP',
            'fecha_emision' => $validados['fecha_emision'],
            'estado' => EstadoCobranza::Registrada,
            'referencia_documento_tributario' => $validados['referencia_documento_tributario'] ?? null,
            'observacion' => $validados['observacion'] ?? null,
            'registrado_por' => $request->user()->id,
        ]);

        return response()->json([
            'mensaje' => 'Cobro registrado. Esta aplicacion NO emitio ningun documento '
                . 'tributario electronico: queda registrado lo que la notaria ya emitio.',
            'cobranza' => $cobranza,
        ], 201);
    }

    public function update(Request $request, Cobranza $cobranza): JsonResponse
    {
        $this->authorize('facturar', $cobranza->expediente);

        $validados = $request->validate([
            'estado' => ['required', 'string', 'in:' . implode(',', EstadoCobranza::opciones())],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        $cobranza->update($validados);

        return response()->json($cobranza->refresh());
    }
}