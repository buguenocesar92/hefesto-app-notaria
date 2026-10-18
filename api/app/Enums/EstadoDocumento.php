<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de un documento del expediente.
 *
 * `Vigente`    : es la version que aplica hoy.
 * `Reemplazado`: sigue en el expediente, pero otra version lo sustituyo.
 *
 * NO existe un estado "eliminado", y la API no expone una ruta para borrar.
 * Un documento de un expediente notarial no se borra: se reemplaza. Ver
 * `App\Services\Documentos\GestorDeDocumentos`.
 */
enum EstadoDocumento: string
{
    case Vigente = 'vigente';
    case Reemplazado = 'reemplazado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::Reemplazado => 'Reemplazado',
        };
    }

    /** @return list<string> */
    public static function opciones(): array
    {
        return array_map(static fn (self $estado): string => $estado->value, self::cases());
    }
}