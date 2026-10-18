<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de un antecedente: algo que hay que conseguir para avanzar.
 *
 * `Pendiente` es el estado que mas duele: se pidio y no llego. Es el motivo
 * mas comun de un expediente parado, y el que este sistema quiere hacer
 * visible.
 *
 * `Rechazado` y `Descartado` NO son lo mismo que `Pendiente`. Un antecedente
 * rechazado o descartado es una decision que la notaria registro; queda en el
 * historial para siempre, porque un expediente que se avanza "saco lo que no
 * sirvio" sin dejar rastro no se puede defender despues.
 */
enum EstadoAntecedente: string
{
    case Pendiente = 'pendiente';
    case Recibido = 'recibido';
    case Rechazado = 'rechazado';
    case Descartado = 'descartado';

    public function espera(): bool
    {
        return $this === self::Pendiente;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Recibido => 'Recibido',
            self::Rechazado => 'Rechazado',
            self::Descartado => 'Descartado',
        };
    }

    /** @return list<string> */
    public static function opciones(): array
    {
        return array_map(static fn (self $estado): string => $estado->value, self::cases());
    }
}