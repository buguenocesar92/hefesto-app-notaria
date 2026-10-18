<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ConfiguracionNotaria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configuracion de la notaria.
 *
 * LA ADVERTENCIA QUE ESTA PANTALLA TIENE QUE MOSTRAR SIEMPRE
 * --------------------------------------------------------
 * 1. Los umbrales (`dias_alerta`, `dias_critico`) son ALARMAS INTERNAS. No
 *    son plazos legales. Un rojo no significa "se vencio".
 *
 * 2. Los `parametros_por_confirmar` vienen VACIOS a proposito. Son los datos
 *    que esta aplicacion NO puede darle, porque darlos seria inventar un
 *    plazo. Los carga un notario de la notaria, y al cargarlos deja
 *    constancia de con que fundamento los fijo.
 *
 * La pantalla no esconde esto: lo muestra al lado del campo, vacio, con la
 * etiqueta de lo que hay que confirmar.
 */
class ConfiguracionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->authorize('ver', ConfiguracionNotaria::class);

        $configuracion = ConfiguracionNotaria::query()->firstOrNew([
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return response()->json([
            'datos' => $configuracion,
            'advertencia' => 'Estos umbrales son una alarma interna de la notaria. '
                . 'NO son plazos legales, y este sistema no conoce ni calcula plazos legales. '
                . 'Un expediente en rojo significa que la etapa lleva mas dias que los que la '
                . 'notaria decidio esperar, no que algo se haya vencido.',
            'parametros' => $this->parametrosPorConfirmar($configuracion),
        ]);
    }

    /**
     * Actualiza los umbrales internos del semaforo.
     */
    public function actualizar(Request $request): JsonResponse
    {
        $this->authorize('actualizar', ConfiguracionNotaria::class);

        $validados = $request->validate([
            'zona_horaria' => ['nullable', 'string', 'max:64'],
            'dias_alerta_por_defecto' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'dias_critico_por_defecto' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'aviso_umbral_no_configurado' => ['nullable', 'string', 'max:250'],
        ], [], ['dias_alerta_por_defecto' => 'umbral de alerta', 'dias_critico_por_defecto' => 'umbral critico']);

        $this->revisarUmbrales($validados);

        $configuracion = ConfiguracionNotaria::query()->firstOrNew([
            'tenant_id' => $request->user()->tenant_id,
        ]);

        $configuracion->fill($validados)->save();

        return response()->json([
            'datos' => $configuracion->refresh(),
            'nota' => 'El cambio NO reescribe los expedientes ya abiertos: copiaron el '
                . 'umbral al abrirse. Solo afecta a los expedientes que se abran de aqui en adelante.',
        ]);
    }

    /**
     * Confirma uno de los parametros que un notario debe cargar.
     *
     * El valor SIEMPRE viene de la notaria. Esta aplicacion no propone uno.
     * Y el fundamento tambien: un dato sin el criterio con el que se fijo no
     * sirve para sostener nada mas adelante.
     */
    public function confirmarParametro(Request $request, string $clave): JsonResponse
    {
        $this->authorize('confirmarParametro', ConfiguracionNotaria::class);

        $configuracion = ConfiguracionNotaria::query()->firstOrNew([
            'tenant_id' => $request->user()->tenant_id,
        ]);

        $parametros = $configuracion->parametros_por_confirmar ?? [];

        if (! array_key_exists($clave, $parametros)) {
            return response()->json([
                'mensaje' => 'Ese parametro no existe en la configuracion de esta notaria.',
            ], 404);
        }

        $validados = $request->validate([
            'valor' => ['required'],
            'fundamento' => ['required', 'string', 'max:1000'],
        ], [], [
            'valor' => 'valor',
            'fundamento' => 'fundamento',
        ]);

        $parametros[$clave]['valor'] = $validados['valor'];
        $parametros[$clave]['fundamento'] = $validados['fundamento'];
        $parametros[$clave]['confirmado_por'] = $request->user()->id;
        $parametros[$clave]['confirmado_en'] = now()->toIso8601String();

        $configuracion->parametros_por_confirmar = $parametros;
        $configuracion->save();

        return response()->json([
            'parametros' => $parametros,
            'nota' => 'Quedo registrado quien lo confirmo y cuando. '
                . 'Esta aplicacion no valida el valor: registra lo que la notaria decidio.',
        ]);
    }

    /**
     * Los parametros que hay que confirmar, marcados como tales.
     *
     * Se siembran SOLO con la etiqueta y `valor: null`. Sembrar un valor de
     * ejemplo seria inventar un plazo, que es exactamente lo que esta
     * aplicacion no puede hacer.
     *
     * @return array<string, array<string, mixed>>
     */
    private function parametrosPorConfirmar(ConfiguracionNotaria $configuracion): array
    {
        $parametros = $configuracion->parametros_por_confirmar;

        if (is_array($parametros) && $parametros !== []) {
            return $parametros;
        }

        return [
            'dias_espera_antecedente_no_respondido' => [
                'etiqueta' => 'Dias que la notaria espera un antecedente antes de alarmarse internamente',
                'valor' => null,
                'fundamento' => null,
                'confirmado_por' => null,
                'confirmado_en' => null,
                'ayuda' => 'Es un criterio INTERNO de la notaria. No es un plazo legal, '
                    . 'y esta aplicacion no conoce ninguno.',
            ],
            'dias_revision_documentacion' => [
                'etiqueta' => 'Dias que la notaria tarda en revisar la documentacion de un expediente',
                'valor' => null,
                'fundamento' => null,
                'confirmado_por' => null,
                'confirmado_en' => null,
                'ayuda' => 'Criterio interno. Esta aplicacion no impone ninguno.',
            ],
            'dias_para_citar_partes' => [
                'etiqueta' => 'Dias que la notaria espera entre una citacion y la firma',
                'valor' => null,
                'fundamento' => null,
                'confirmado_por' => null,
                'confirmado_en' => null,
                'ayuda' => 'Criterio interno de la notaria. Si este dato depende de una '
                    . 'norma, el fundamento es lo que un notario deja escrito aca para '
                    . 'poder demostrar cual fue.',
            ],
        ];
    }

    /** @param array<string, mixed> $validados */
    private function revisarUmbrales(array $validados): void
    {
        $alerta = $validados['dias_alerta_por_defecto'] ?? null;
        $critico = $validados['dias_critico_por_defecto'] ?? null;

        if ($alerta !== null && $critico !== null && $critico < $alerta) {
            abort(422, 'El umbral critico no puede ser menor que el de alerta.');
        }
    }
}