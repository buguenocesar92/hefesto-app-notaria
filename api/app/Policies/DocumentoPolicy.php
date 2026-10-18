<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Documento;
use App\Models\Usuario;

/**
 * Permisos sobre documentos de un expediente.
 *
 * LA REGLA QUE NO SE ROMPE
 * ------------------------
 * Ningun rol, ni siquiera el administrador, puede BORRAR un documento de un
 * expediente. No existe `delete` en esta policy, y la API no expone DELETE
 * de documentos. Lo que hay es reemplazar y marcar como reemplazado.
 *
 * Un expediente notarial sin su historial documental no vale nada.
 */
class DocumentoPolicy
{
    /** Ver el documento y su historial de versiones. */
    public function view(Usuario $usuario, Documento $documento): bool
    {
        return true;
    }

    /** Descargar un archivo: se autoriza antes de tocar el disco. */
    public function descargar(Usuario $usuario, Documento $documento): bool
    {
        return true;
    }

    /** Subir la primera version de un documento nuevo. */
    public function crear(Usuario $usuario): bool
    {
        return $usuario->rol->mueveElTramite();
    }

    /**
     * Subir una version nueva.
     *
     * La version anterior NO se borra: queda con `vigente_hasta` distinto de
     * NULL. Por eso la accion se llama agregarVersion y no reemplazar.
     */
    public function agregarVersion(Usuario $usuario, Documento $documento): bool
    {
        return $usuario->rol->mueveElTramite();
    }

    /**
     * Marcar un documento como reemplazado.
     *
     * Es la accion MAS FUERTE sobre un documento sin borrarlo, asi que la
     * reservamos al notario y al administrador de la notaria.
     */
    public function marcarReemplazado(Usuario $usuario, Documento $documento): bool
    {
        return $usuario->rol->esNotario();
    }
}