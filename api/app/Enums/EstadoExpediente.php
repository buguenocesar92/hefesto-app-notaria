<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado global de un expediente.
 *
 * `EnTramite` y `Cerrado` son los dos estados normales. `Anulado` existe
 * para que un expediente mal abierto no se borre, sino que quede marcado.
 *
 * El estado global NO se escribe a mano: se deriva de las etapas con
 * `App\Services\Expedientes\EstadoDeExpediente`. Que alguien pueda elegir
 * "cerrado" con etapas abiertas por el camino es exactamente el tipo de dato
 * que despues nadie sabe defender.
 *
 * `Cerrado` NO significa que la operacion quedo valida. Significa que la
 * notaria dio el tramite por terminado. El sistema no dice si lo que se firmo
 * era correcto: no puede.
 */
enum EstadoExpediente: string
{
    case EnTramite = 'en_tramite';
    case Cerrado = 'cerrado';
    case Anulado = 'anulado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EnTramite => 'En tramite',
            self::Cerrado => 'Cerrado',
            self::Anulado => 'Anulado',
        };
    }

    /** @return list<string> */
    public static function opciones(): array
    {
        return array_map(static fn (self $estado): string => $estado->value, self::cases());
    }
}