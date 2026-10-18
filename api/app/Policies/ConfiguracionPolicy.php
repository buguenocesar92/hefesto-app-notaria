<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TipoOperacion;
use App\Models\Usuario;

/**
 * Permisos sobre la configuracion de la notaria.
 *
 * Los umbrales del semaforo son el parametro mas sensible de la aplicacion,
 * porque son lo que hace que aparezcan o no las alertas. Configurarlos es de
 * quien responde por el tramite: el notario o el administrador de la notaria.
 *
 * Un auxiliar que cambiara el umbral de 5 a 30 diaseria estar apagando
 * alarmas, sin querer y sin que nadie lo notara. Por eso esta clase no se
 * lo permite.
 */
class ConfiguracionPolicy
{
    public function ver(Usuario $usuario): bool
    {
        return true;
    }

    /**
     * Cambiar los umbrales del semaforo.
     *
     * Recordar que cambiar el umbral por defecto NO reescribe los expedientes
     * ya abiertos: copiaron el valor al abrirse.
     */
    public function actualizar(Usuario $usuario): bool
    {
        return $usuario->rol->esNotario();
    }

    /**
     * Cargar los datos que un notario debe confirmar.
     *
     * Estos son los datos mas sensibles del sistema, justamente porque NO
     * vienen: llegan vacios, y quien los carga deja constancia de con que
     * fundamento los fijo.
     */
    public function confirmarParametro(Usuario $usuario): bool
    {
        return $usuario->rol->esNotario();
    }

    /** Dar de alta un tipo de operacion y su plantilla de etapas. */
    public function configurarTipoOperacion(Usuario $usuario, TipoOperacion $tipoOperacion): bool
    {
        return $usuario->rol->administraLaNotaria();
    }
}