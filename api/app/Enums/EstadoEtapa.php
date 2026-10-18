<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una etapa del tramite.
 *
 * Estados que CUENTAN para el semaforo: `pendiente`, `en_curso`,
 * `bloqueada`. Son los que se pueden quedar esperando.
 *
 * Estados que NO cuentan: `terminada` y `omitida`. Una etapa terminada hace
 * tres semanas no es un atraso: es un atraso que la notaria ya resolvio, y
 * marcarla en rojo seria mentira. Por eso el semaforo los devuelve en gris.
 *
 * `bloqueada` se separo de `pendiente` a proposito: una etapa bloqueada
 * espera por una cosa concreta y en general tiene un antecedente asociado.
 * Mezclar los dos hacia imposible ver en el tablero "esto espera porque nadie
 * fue a buscar algo", que es justamente el problema que el producto viene a
 * resolver.
 */
enum EstadoEtapa: string
{
    case Pendiente = 'pendiente';
    case EnCurso = 'en_curso';
    case Bloqueada = 'bloqueada';
    case Terminada = 'terminada';
    case Omitida = 'omitida';

    /** Estados en los que una etapa puede estar esperando. */
    public function espera(): bool
    {
        return in_array($this, [self::Pendiente, self::EnCurso, self::Bloqueada], true);
    }

    /** Estado al que se vuelve la etapa si hay que devolverla atras. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnCurso => 'En curso',
            self::Bloqueada => 'Bloqueada',
            self::Terminada => 'Terminada',
            self::Omitida => 'Omitida',
        };
    }

    /** @return list<string> */
    public static function opciones(): array
    {
        return array_map(static fn (self $estado): string => $estado->value, self::cases());
    }
}