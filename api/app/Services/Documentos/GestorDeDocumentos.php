<?php

declare(strict_types=1);

namespace App\Services\Documentos;

use App\Enums\EstadoDocumento;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Regla central de los documentos:
 *
 *     NUNCA SE BORRA UN DOCUMENTO ASOCIADO A UN EXPEDIENTE.
 *
 * Un expediente notarial sin su historial documental no vale nada. Si el
 * sistema permite borrar un documento, deja de ser posible reconstruir que
 * se firmo, cuando, y con que version. Por eso NO existe un metodo `borrar`,
 * NO hay una ruta DELETE en la API, y no hay ninguna forma de que la capa de
 * servicios borre un documento o una version.
 *
 * Lo que se hace:
 *   - SUBIR UNA VERSION NUEVA. La version anterior queda con `vigente_hasta`
 *     distinto de NULL: dejo de ser la buena, pero sigue ahi.
 *   - MARCAR COMO REEMPLAZADO. La ficha del documento queda en estado
 *     `reemplazado`, con la fecha y con quien lo hizo.
 *
 * Los archivos fisicos no se borran automaticamente. Ver docs/08.
 */
class GestorDeDocumentos
{
    /** Carpeta dentro del volumen donde se guardan los archivos. */
    private const CARPETA = 'expedientes';

    /**
     * Sube la primera version de un documento nuevo.
     */
    public function crear(
        string $tenantId,
        string $expedienteId,
        UploadedFile $archivo,
        string $nombre,
        ?string $tipo = null,
        ?string $fechaDocumento = null,
        ?string $usuarioId = null,
    ): Documento {
        return DB::transaction(function () use (
            $tenantId,
            $expedienteId,
            $archivo,
            $nombre,
            $tipo,
            $fechaDocumento,
            $usuarioId
        ): Documento {
            $documento = Documento::create([
                'tenant_id' => $tenantId,
                'expediente_id' => $expedienteId,
                'nombre' => $nombre,
                'tipo' => $tipo,
                'estado' => EstadoDocumento::Vigente,
                'version_actual' => 1,
                'fecha_documento' => $fechaDocumento,
                'creado_por' => $usuarioId,
            ]);

            $this->guardarVersion($documento, $archivo, 1, $usuarioId);

            return $documento->refresh();
        });
    }

    /**
     * Sube una version nueva de un documento existente.
     *
     * NO REEMPLAZA EL ARCHIVO ANTERIOR: lo marca como no vigente. El archivo
     * viejo sigue en disco y en la base, y se puede descargar todavia.
     */
    public function agregarVersion(
        Documento $documento,
        UploadedFile $archivo,
        ?string $usuarioId = null,
        ?string $fechaDocumento = null,
        ?string $observacion = null,
    ): DocumentoVersion {
        return DB::transaction(function () use ($documento, $archivo, $usuarioId, $fechaDocumento, $observacion): DocumentoVersion {
            $siguiente = ((int) $documento->version_actual) + 1;

            // La version vigente deja de serlo. Se le pone fecha, no se borra.
            DocumentoVersion::query()
                ->where('documento_id', $documento->id)
                ->whereNull('vigente_hasta')
                ->update([
                    'vigente_hasta' => now(),
                    'updated_at' => now(),
                ]);

            $version = $this->guardarVersion($documento, $archivo, $siguiente, $usuarioId, $fechaDocumento, $observacion);

            $documento->forceFill([
                'version_actual' => $siguiente,
                'estado' => EstadoDocumento::Vigente,
                'reemplazado_en' => null,
                'reemplazado_por' => null,
                'reemplazado_por_documento_id' => null,
            ])->save();

            return $version;
        });
    }

    /**
     * Marca un documento como reemplazado por otro.
     *
     * NO lo borra. Queda en el expediente, con la fecha, para que se pueda
     * demostrar que existio y cuando dejo de estar vigente.
     */
    public function marcarReemplazado(
        Documento $documento,
        string $usuarioId,
        ?string $reemplazadoPorDocumentoId = null,
        ?string $motivo = null,
    ): Documento {
        return DB::transaction(function () use ($documento, $usuarioId, $reemplazadoPorDocumentoId, $motivo): Documento {
            $documento->forceFill([
                'estado' => EstadoDocumento::Reemplazado,
                'reemplazado_por' => $usuarioId,
                'reemplazado_en' => now(),
                'reemplazado_por_documento_id' => $reemplazadoPorDocumentoId,
                'observacion' => $motivo !== null ? $motivo : $documento->observacion,
            ])->save();

            return $documento->refresh();
        });
    }

    /**
     * Guarda el archivo en el volumen y registra la version.
     */
    private function guardarVersion(
        Documento $documento,
        UploadedFile $archivo,
        int $numeroVersion,
        ?string $usuarioId,
        ?string $fechaDocumento = null,
        ?string $observacion = null,
    ): DocumentoVersion {
        // La ruta es RELATIVA al volumen y lleva el tenant y el expediente en
        // el medio: dos notarias pueden tener un documento con el mismo
        // nombre sin pisarse.
        $extension = strtolower($archivo->getClientOriginalExtension() ?: 'bin');

        $ruta = sprintf(
            '%s/%s/%s/v%d-%s.%s',
            self::CARPETA,
            $documento->tenant_id,
            $documento->expediente_id,
            $numeroVersion,
            $documento->id,
            $extension,
        );

        Storage::disk('documentos')->put($ruta, (string) file_get_contents($archivo->getRealPath()));

        return DocumentoVersion::create([
            'tenant_id' => $documento->tenant_id,
            'documento_id' => $documento->id,
            'version' => $numeroVersion,
            'fecha_documento' => $fechaDocumento,
            'ruta_archivo' => $ruta,
            'nombre_archivo' => $archivo->getClientOriginalName(),
            'mime' => $archivo->getClientMimeType(),
            'tamano_bytes' => $archivo->getSize(),
            'hash_sha256' => hash_file('sha256', $archivo->getRealPath()) ?: null,
            'subido_por' => $usuarioId,
            'vigente_hasta' => null,
            'observacion' => $observacion,
        ]);
    }

    /**
     * Resuelve la ruta ABSOLUTA de un archivo, dejando constancia de que no
     * se sale del volumen.
     *
     * La RLS protege el dato, pero no el archivo: si se escribiera una ruta
     * maliciosa en la base (`../../etc/passwd`), el filtro de la base no
     * ayuda. Por eso se comprueba aqui que la ruta resuelta siga dentro del
     * volumen.
     */
    public function rutaAbsolutaSegura(DocumentoVersion $version): string
    {
        $raiz = rtrim((string) config('documentos.ruta'), DIRECTORY_SEPARATOR);
        $absoluta = realpath($raiz . DIRECTORY_SEPARATOR . $version->ruta_archivo);

        if ($absoluta === false) {
            throw new RuntimeException('El archivo del documento no existe en el volumen.');
        }

        if (! str_starts_with($absoluta, $raiz)) {
            throw new RuntimeException('La ruta del documento sale del volumen de documentos.');
        }

        return $absoluta;
    }
}