<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AgendaCita;
use App\Models\Usuario;

/**
 * Permisos sobre la agenda.
 *
 * Todos los roles de la notaria pueden mirar y agendar: agendar es lo mas
 * basico del trabajo. Cancelar una cita ya realizada es otra cosa, y queda
 * en manos de quien responde por el tramite.
 */
class AgendaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    public function view(Usuario $usuario, AgendaCita $cita): bool
    {
        return true;
    }

    public function crear(Usuario $usuario): bool
    {
        return true;
    }

    public function actualizar(Usuario $usuario, AgendaCita $cita): bool
    {
        return true;
    }

    /** Cancelar: puede deshacer una cita propia de cualquiera. */
    public function cancelar(Usuario $usuario, AgendaCita $cita): bool
    {
        return $usuario->rol->mueveElTramite();
    }
}