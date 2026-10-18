<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una cita de agenda.
 *
 * `Bloqueo` es la unica que no es una atencion a una persona: es tiempo que
 * la notaria se guarda para si (reunion interna, perpetual, alimentacion).
 * Los bloqueos se muestran en la agenda para que no se agende encima.
 */
enum EstadoCita: string
{
    case Propuesta = 'propuesta';
    case Confirmada = 'confirmada';
    case Realizada = 'realizada';
    case Cancelada = 'cancelada';
    case Bloqueo = 'bloqueo';

    public function ocupaAgenda(): bool
    {
        return in_array($this, [self::Propuesta, self::Confirmada, self::Realizada], true);
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Propuesta => 'Propuesta',
            self::Confirmada => 'Confirmada',
            self::Realizada => 'Realizada',
            self::Cancelada => 'Cancelada',
            self::Bloqueo => 'Bloqueo',
        };
    }

    /** @return list<string> */
    public static function opciones(): array
    {
        return array_map(static fn (self $estado): string => $estado->value, self::cases());
    }
}