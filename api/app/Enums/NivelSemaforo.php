<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nivel del semaforo de una etapa.
 *
 * LO QUE ESTOS COLORES NO SON
 * ---------------------------
 * No son plazos legales. No son "vencimientos". No son una prediccion de
 * cuando el tramite deberia estar listo.
 *
 * Son una alarma interna que la notaria se configuro a si misma. Un rojo
 * quiere decir: "esta etapa lleva N dias en este estado y a la notaria le
 * conviene mirarla". Nada mas.
 *
 * Los casos:
 *   - `SinUmbral`   : la notaria no configuro N. NO se marca nada. Antes que
 *                     inventar un numero, no marcar.
 *   - `Verde`       : dentro del umbral que la notaria definio.
 *   - `Amarillo`    : supero `dias_alerta`.
 *   - `Rojo`        : supero `dias_critico`.
 *   - `Terminado`   : la etapa ya termino o se omito. No es atraso.
 */
enum NivelSemaforo: string
{
    case SinUmbral = 'sin_umbral';
    case Verde = 'verde';
    case Amarillo = 'amarillo';
    case Rojo = 'rojo';
    case Terminado = 'terminado';

    public function estaAtrasada(): bool
    {
        return in_array($this, [self::Amarillo, self::Rojo], true);
    }

    /**
     * Orden de urgencia, para ordenar el tablero de mas urgente a menos.
     * `SinUmbral` va al final: no es urgente, es invisible.
     */
    public function urgencia(): int
    {
        return match ($this) {
            self::Rojo => 4,
            self::Amarillo => 3,
            self::Verde => 2,
            self::SinUmbral => 1,
            self::Terminado => 0,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::SinUmbral => 'Sin umbral configurado',
            self::Verde => 'En plazo interno',
            self::Amarillo => 'Atrasada',
            self::Rojo => 'Muy atrasada',
            self::Terminado => 'Terminada',
        };
    }

    /** @return list<string> */
    public static function opciones(): array
    {
        return array_map(static fn (self $nivel): string => $nivel->value, self::cases());
    }
}