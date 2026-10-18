<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RolUsuario;
use App\Models\Expediente;
use App\Models\Usuario;

/**
 * Permisos sobre un expediente, dentro de una notaria.
 *
 * La RLS ya garantiza que nadie ve expedientes de otra notaria. Esta clase
 * limita DENTRO de la notaria.
 *
 * CUANDO UNA POLICY RECHAZA, LA RESPUESTA ES 404, NO 403
 * ------------------------------------------------------
 * Un 403 confirmaria que el recurso existe. Como la RLS ya impide que exista
 * a ojos de la otra notaria, 404 es ademas la respuesta correcta.
 *
 * QUE ES UN `administrador`
 * -------------------------
 * Es el rol que administra LA APLICACION para la notaria: puede configurar
 * umbrales, tipos de operacion y usuarios. NO es el notario con mas poder
 * juridico, y no reemplaza ninguna facultad legal. Ver docs/03.
 */
class ExpedientePolicy
{
    /**
     * Todos los roles ven los expedientes de la notaria.
     *
     * Es una decision de la notaria, no una regla tecnica. Si la notaria
     * necesita que un auxiliar no vea certain expedientes, se cambia aqui
     * explicitamente; no se infiere del rol.
     */
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    public function view(Usuario $usuario, Expediente $expediente): bool
    {
        return true;
    }

    /** Abrir un expediente: cualquiera de la notaria. */
    public function crear(Usuario $usuario): bool
    {
        return true;
    }

    /** Editar los datos del expediente. */
    public function actualizar(Usuario $usuario, Expediente $expediente): bool
    {
        return $usuario->rol->mueveElTramite();
    }

    /**
     * Mover el tramite: cambiar el estado de una etapa, pedir antecedentes.
     */
    public function moverEtapa(Usuario $usuario, Expediente $expediente): bool
    {
        return $usuario->rol->mueveElTramite();
    }

    /**
     * Cerrar o anular un expediente.
     *
     * Reservado al notario y al administrador de la notaria: es la accion
     * que dice "esto se dio por terminado", y conviene que la ejecute quien
     * responde por el tramite.
     *
     * Cerrar NO declara valida la operacion. El sistema no puede hacerlo, y
     * por eso el nombre de la accion es "cerrar" y no "validar" ni
     * "legalizar".
     */
    public function cerrar(Usuario $usuario, Expediente $expediente): bool
    {
        return $usuario->rol->esNotario();
    }

    /** Registrar cobros. */
    public function facturar(Usuario $usuario, Expediente $expediente): bool
    {
        return $usuario->rol->registraCobros();
    }

    /** Anular el expediente: no se borra, se marca. */
    public function anular(Usuario $usuario, Expediente $expediente): bool
    {
        return $usuario->rol->esNotario();
    }
}