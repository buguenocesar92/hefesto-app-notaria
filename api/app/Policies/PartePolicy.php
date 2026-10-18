<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Parte;
use App\Models\Usuario;

/**
 * Permisos sobre las partes de una operacion.
 *
 * DATO SENSIBLE
 * -------------
 * Las partes son personas identificables en operaciones sobre patrimonio,
 * familia y sucesiones. Estan protegidas por la Ley 19.628.
 *
 * Ver a todos los roles de la notaria es una decision operativa: el auxiliar
 * que agenda necesita saber a quien llama. Lo que NO puede el auxiliar es
 * editar o eliminar los datos de una parte.
 */
class PartePolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    public function view(Usuario $usuario, Parte $parte): bool
    {
        return true;
    }

    /** Crear una parte y asignarle un rol en un expediente. */
    public function crear(Usuario $usuario): bool
    {
        return $usuario->rol->mueveElTramite();
    }

    /**
     * Editar los datos de una persona.
     *
     * Exigido a notario, protocolo y administrador: son los datos que no se
     * tocan a la ligera.
     */
    public function actualizar(Usuario $usuario, Parte $parte): bool
    {
        return $usuario->rol->mueveElTramite();
    }
}