<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Rol dentro de la notaria.
 *
 * NO es lo mismo que el grupo de Authentik. El grupo de Authentik sirve
 * unicamente para el primer alta. De ahi en adelante, el permiso lo da este
 * rol, que es lo que la notaria controla desde la aplicacion.
 *
 * QUE SABE Y QUE NO SABE ESTA APLICACION
 * --------------------------------------
 * `Notario` y `Protocolo` son nombres de cargo. Este sistema NO sabe que
 * faculties tiene un notario segun la ley aplicable, ni las restringe por si
 * mismo: lo que puede hacer cada rol es lo que dice la tabla de docs/03, que
 * es una decision de la notaria y que un notario puede cambiar. Si el marco
 * aplicable exige otras restricciones, hay que cambiarlas explicitamente, no
 * confiar en que el nombre del rol las implica.
 */
enum RolUsuario: string
{
    case Administrador = 'administrador';
    case Notario = 'notario';
    case Protocolo = 'protocolo';
    case Auxiliar = 'auxiliar';

    /** El administrador de la notaria: configura, y ve todo. */
    public function administraLaNotaria(): bool
    {
        return $this === self::Administrador;
    }

    /** Puede abrir y cerrar expedientes, y reemplazar documentos. */
    public function esNotario(): bool
    {
        return in_array($this, [self::Administrador, self::Notario], true);
    }

    /** Puede mover el tramite: cambiar estados de etapas y pedir antecedentes. */
    public function mueveElTramite(): bool
    {
        return in_array($this, [self::Administrador, self::Notario, self::Protocolo], true);
    }

    /** Puede registrar cobros. */
    public function registraCobros(): bool
    {
        return $this->esNotario();
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador de la notaria',
            self::Notario => 'Notario',
            self::Protocolo => 'Protocolista',
            self::Auxiliar => 'Auxiliar',
        };
    }

    /** @return list<string> */
    public static function opciones(): array
    {
        return array_map(static fn (self $rol): string => $rol->value, self::cases());
    }
}