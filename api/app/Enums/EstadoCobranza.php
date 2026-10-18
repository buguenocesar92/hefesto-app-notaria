<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de un cobro registrado.
 *
 * ESTA APLICACION NO EMITE DOCUMENTOS TRIBUTARIOS ELECTRONICOS
 * -----------------------------------------------------------
 * Hefesto tiene el kit LibreDTE, pero su version publicada es una beta y su
 * API es de solo lectura: no permite automatizar la emision. Por eso aqui no
 * existe ningun estado que represente un documento electronicamente emitido,
 * timbrado, anulado o enviado. Lo que hay es el REGISTRO de lo que la
 * notaria ya emitio por su cuenta.
 *
 * Ver docs/05, seccion "Facturacion electronica: lo que no se hace".
 */
enum EstadoCobranza: string
{
    case Registrada = 'registrada';
    case Pagada = 'pagada';
    case Anulada = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Registrada => 'Registrada',
            self::Pagada => 'Pagada',
            self::Anulada => 'Anulada',
        };
    }

    /** @return list<string> */
    public static function opciones(): array
    {
        return array_map(static fn (self $estado): string => $estado->value, self::cases());
    }
}