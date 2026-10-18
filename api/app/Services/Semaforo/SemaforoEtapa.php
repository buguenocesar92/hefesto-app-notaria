<?php

declare(strict_types=1);

namespace App\Services\Semaforo;

use App\Enums\EstadoEtapa;
use App\Enums\NivelSemaforo;
use Carbon\CarbonImmutable;

/**
 * El semaforo de una etapa, calculado. Es un objeto de VALOR: no toca la base
 * de datos, no lee la hora del sistema por su cuenta y no depende de nada.
 * Todo lo que necesita le llega por el constructor.
 *
 * QUE ES Y QUE NO ES ESTE SEMAFORO
 * --------------------------------
 * ES: una alarma interna que la notaria se configuro a si misma. Compara los
 *     dias que la etapa lleva en su estado actual contra un umbral (N) que la
 *     notaria definio. Si lo supera, la etapa aparece en amarillo, y en rojo
 *     si supera el segundo umbral.
 *
 * NO ES: un plazo legal. NO ES un vencimiento. NO ES una prediccion de cuando
 *         el tramite deberia estar listo. NO sabe nada de la ley, y no puede:
 *         una aplicacion no interpreta la ley. Lo unico que compara son dos
 *         numeros que la notaria le dio.
 *
 * LA REGLA MAS IMPORTANTE DE TODA LA CLASE
 * ---------------------------------------
 * Si la notaria no configuro N (`dias_alerta` en NULL), el nivel es
 * `SinUmbral` y NO SE MARCA NADA. No hay valor por omision, ni "tres dias
 * como default sensato", ni nada.
 *
 * Esa es la diferencia entre una herramienta y un riesgo. Si el sistema
 * marcara en rojo con un umbral inventado, una notaria leeria "esto se
 * vencido" y moveria plazos de personas reales en funcion de un numero que
 * salio de la nada. Marcar en rojo solo cuando la notaria dijo "a mas de N
 * dias mandame una alerta" no dice nada falso: solo recuerda lo que la propia
 * notaria pidio recordar.
 */
final readonly class SemaforoEtapa
{
    public function __construct(
        public NivelSemaforo $nivel,
        /** Dias completos que la etapa lleva en su estado actual. */
        public int $diasEnEstado,
        /** Umbral de alerta (N) usado, o null si la notaria no lo definio. */
        public ?int $diasAlerta,
        /** Umbral critico usado, o null si no aplica. */
        public ?int $diasCritico,
        /** Desde cuando se cuenta: inicio del estado, o inicio del expediente. */
        public ?CarbonImmutable $referencia,
    ) {}

    /**
     * Calcula el semaforo de una etapa.
     *
     * @param  EstadoEtapa          $estado      estado actual de la etapa
     * @param  int|null             $diasAlerta  umbral N, o null si no hay
     * @param  int|null             $diasCritico umbral critico, o null
     * @param  CarbonImmutable|null $referencia  desde cuando se cuenta
     * @param  CarbonImmutable|null $ahora       momento de referencia
     */
    public static function calcular(
        EstadoEtapa $estado,
        ?int $diasAlerta,
        ?int $diasCritico,
        ?CarbonImmutable $referencia,
        ?CarbonImmutable $ahora = null,
    ): self {
        $ahora ??= CarbonImmutable::now();

        // 1. Una etapa que ya termino o se omito no es un atraso, por muy
        //    vieja que sea. Marcar en rojo algo que la notaria ya resolvio
        //    seria mentirle.
        if (! $estado->espera()) {
            return new self(
                nivel: NivelSemaforo::Terminado,
                diasEnEstado: self::diasTranscurridos($referencia, $ahora),
                diasAlerta: $diasAlerta,
                diasCritico: $diasCritico,
                referencia: $referencia,
            );
        }

        $dias = self::diasTranscurridos($referencia, $ahora);

        // 2. Sin umbral configurado, no se marca nada. Este es el caso que
        //    protege a la notaria de un numero inventado.
        if ($diasAlerta === null) {
            return new self(
                nivel: NivelSemaforo::SinUmbral,
                diasEnEstado: $dias,
                diasAlerta: null,
                diasCritico: $diasCritico,
                referencia: $referencia,
            );
        }

        // 3. Si la notaria no definio un umbral critico aparte, el critico es
        //    el mismo que el de alerta: no hay rojo. Es una decision
        //    deliberada. Un rojo por omision seria inventar un segundo plazo.
        //
        //    Si por error de configuracion el critico queda MENOR que la
        //    alerta, se usa el mas alto (la alerta): una etapa nunca puede
        //    estar en rojo sin haber pasado antes por amarillo.
        $critico = $diasCritico === null ? $diasAlerta : max($diasCritico, $diasAlerta);

        $nivel = match (true) {
            $critico > $diasAlerta && $dias >= $critico => NivelSemaforo::Rojo,
            $dias >= $diasAlerta => NivelSemaforo::Amarillo,
            default => NivelSemaforo::Verde,
        };

        return new self(
            nivel: $nivel,
            diasEnEstado: $dias,
            diasAlerta: $diasAlerta,
            diasCritico: $diasCritico,
            referencia: $referencia,
        );
    }

    /**
     * Dias COMPLETOS entre `$referencia` y `$ahora`.
     *
     * Se cuenta dias completos, no "dias redondeados": una etapa que lleva
     * tres horas no esta en un dia, y marcarla en un dia seria correr el
     * reloj por gusto.
     *
     * Sin `$referencia` (etapa que nunca empezo y sin fecha de respaldo), se
     * devuelven cero dias. No se inventa una fecha.
     */
    public static function diasTranscurridos(
        ?CarbonImmutable $referencia,
        ?CarbonImmutable $ahora = null,
    ): int {
        if ($referencia === null) {
            return 0;
        }

        $ahora ??= CarbonImmutable::now();

        // Si la referencia esta en el futuro (un reloj desfasado, o una fecha
        // mal digitada), no se resta un numero negativo: se cuenta cero.
        if ($referencia->isFuture()) {
            return 0;
        }

        return (int) $referencia->diffInDays($ahora);
    }

    /** El semaforo marca esta etapa como atrasada. */
    public function estaAtrasada(): bool
    {
        return $this->nivel->estaAtrasada();
    }

    /**
     * Texto para mostrarle a una persona de la notaria.
     *
     * Dice "lleva N dias", que es un hecho. Nunca dice "se vencido" ni
     * "vence en", que serian afirmaciones legales que este sistema no puede
     * hacer.
     */
    public function resumen(): string
    {
        return match ($this->nivel) {
            NivelSemaforo::Terminado => 'Etapa terminada.',
            NivelSemaforo::SinUmbral => 'Sin umbral configurado: esta notaria todavia no '
                . 'definio cuantos dias espera antes de alarmarse. No se marca nada.',
            default => sprintf(
                'Lleva %d dias en este estado. Umbral interno de la notaria: %d dias.',
                $this->diasEnEstado,
                $this->diasAlerta ?? 0
            ),
        };
    }
}