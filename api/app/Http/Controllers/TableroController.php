<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoEtapa;
use App\Enums\NivelSemaforo;
use App\Models\AlertaSemaforo;
use App\Models\Antecedente;
use App\Models\ConfiguracionNotaria;
use App\Models\Etapa;
use App\Models\Expediente;
use App\Services\Semaforo\RevisorDeSemaforo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * EL TABLERO: la pantalla que justifica el producto.
 *
 * La pregunta que responde es una sola: QUE ESTA ATRASADO HOY.
 *
 * Esta pantalla NO REPARTA PLAZOS LEGALES, Y DICHO ESO EN VOZ ALTA
 * ---------------------------------------------------------------
 * Todos los dias, en esa pantalla, hay etapas en rojo. Si una notaria llegara
 * a leer el rojo como "esto se vencio", el sistema estaria haciendo
 * exactamente lo que no debe: dar un numero con apariencia legal a un
 * calculo interno.
 *
 * Por eso:
 *   - El nivel se compara contra un umbral que la NOTARIA configuro.
 *   - Sin umbral configurado, no se marca nada y se dice por que.
 *   - Cada fila dice "lleva N dias en este estado", que es un hecho, y no
 *     "vence en", que seria una afirmacion que el sistema no puede hacer.
 */
class TableroController extends Controller
{
    public function __construct(private readonly RevisorDeSemaforo $revisor) {}

    /**
     * El tablero del dia.
     */
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $configuracion = ConfiguracionNotaria::query()->first();

        // Si la notaria todavia no configuro el umbral, el tablero se entrega
        // igual pero VACIO y con la advertencia. Es preferible a entregar un
        // tablero con colores inventados a partir de un numero que nadie eligio.
        if ($configuracion === null || $configuracion->sinUmbral()) {
            return response()->json([
                'sin_umbral' => true,
                'aviso' => $configuracion?->aviso_umbral_no_configurado
                    ?? 'Esta notaria todavia no configuro cuantos dias espera antes de alarmarse. '
                        . 'El semaforo no marca nada porque no hay ningun umbral que comparar. '
                        . 'Configurelo en Configuracion. No es un plazo legal: es una alarma interna.',
                'items' => [],
                'conteo' => ['rojo' => 0, 'amarillo' => 0, 'verde' => 0],
            ]);
        }

        // Se cruza lo que quedo guardado con una pasada fresca: el tablero no
        // puede depender solo de la ultima corrida del cron, porque una
        // persona abre esta pantalla a las 9 de la manana y el cron corrio a
        // las 3 de la tarde del dia anterior.
        $this->revisor->revisar();

        $atrasadas = Etapa::query()
            ->esperando()
            ->with(['expediente.tipoOperacion'])
            ->get()
            ->map(static fn (Etapa $etapa): array => [
                'etapa_id' => $etapa->id,
                'expediente_id' => $etapa->expediente_id,
                'folio' => $etapa->expediente?->folio,
                'numero_protocolo' => $etapa->expediente?->numero_protocolo,
                'tipo_operacion' => $etapa->expediente?->tipoOperacion?->nombre,
                'etapa' => $etapa->nombre,
                'estado' => $etapa->estado->value,
                'nivel' => $etapa->semaforo()->nivel->value,
                'dias_en_estado' => $etapa->semaforo()->diasEnEstado,
                'dias_alerta' => $etapa->dias_alerta,
                'dias_critico' => $etapa->dias_critico,
                'antecedentes_pendientes' => $etapa->requiere_antecedente
                    ? Antecedente::query()
                        ->where('etapa_id', $etapa->id)
                        ->where('estado', 'pendiente')
                        ->count()
                    : 0,
            ])
            ->filter(static fn (array $fila): bool => $fila['nivel'] !== NivelSemaforo::Verde->value)
            ->sortByDesc('dias_en_estado')
            ->values();

        return response()->json([
            'sin_umbral' => false,
            'items' => $atrasadas,
            'conteo' => [
                'rojo' => $atrasadas->where('nivel', NivelSemaforo::Rojo->value)->count(),
                'amarillo' => $atrasadas->where('nivel', NivelSemaforo::Amarillo->value)->count(),
                'verde' => 0,
            ],
            'alertas_abiertas' => AlertaSemaforo::query()
                ->abiertas()
                ->where('nivel', NivelSemaforo::Rojo->value)
                ->count(),
            'expedientes_abiertos' => Expediente::query()->abiertos()->count(),
            'antecedentes_pendientes' => Antecedente::query()->esperando()->count(),
        ]);
    }

    /**
     * Las etapas de un expediente, en orden, con su semaforo.
     */
    public function etapasDeExpediente(Expediente $expediente): JsonResponse
    {
        $etapas = $expediente->etapas()
            ->with('antecedentes')
            ->get()
            ->map(static fn (Etapa $etapa): array => [
                'id' => $etapa->id,
                'orden' => $etapa->orden,
                'nombre' => $etapa->nombre,
                'estado' => $etapa->estado->value,
                'nivel' => $etapa->semaforo()->nivel->value,
                'dias_en_estado' => $etapa->semaforo()->diasEnEstado,
                'dias_alerta' => $etapa->dias_alerta,
                'iniciada_en' => $etapa->iniciada_en?->toIso8601String(),
                'terminada_en' => $etapa->terminada_en?->toIso8601String(),
                'antecedentes_pendientes' => $etapa->antecedentes
                    ->where('estado', 'pendiente')
                    ->count(),
            ]);

        return response()->json([
            'expediente_id' => $expediente->id,
            'etapas' => $etapas,
            'todos_las_etapas_terminadas' => $etapas->every(
                static fn (array $etapa): bool => $etapa['estado'] === EstadoEtapa::Terminada->value
            ),
        ]);
    }
}