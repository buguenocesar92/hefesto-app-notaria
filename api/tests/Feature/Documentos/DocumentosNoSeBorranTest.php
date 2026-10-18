<?php

declare(strict_types=1);

namespace Tests\Feature\Documentos;

use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\Expediente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LA REGLA: UN DOCUMENTO DE UN EXPEDIENTE NUNCA SE BORRA.
 *
 * Un expediente notarial sin su historial documental no vale nada. Estas
 * pruebas comprueban las dos unicas operaciones que existen sobre un documento
 * (agregar version y marcar como reemplazado) y que ninguna borra.
 */
class DocumentosNoSeBorranTest extends TestCase
{
    use RefreshDatabase;

    public function test_agregar_una_version_no_borra_la_anterior(): void
    {
        $notaria = $this->crearNotaria();
        $expediente = $this->expediente($notaria);

        $documento = Documento::factory()->create([
            'tenant_id' => $notaria['notaria']->id,
            'expediente_id' => $expediente->id,
            'version_actual' => 1,
        ]);

        $primera = DocumentoVersion::create([
            'tenant_id' => $notaria['notaria']->id,
            'documento_id' => $documento->id,
            'version' => 1,
            'ruta_archivo' => 'ejemplo/primera.pdf',
            'nombre_archivo' => 'primera.pdf',
            'vigente_hasta' => null,
        ]);

        // Se agrega la segunda version.
        $primera->update(['vigente_hasta' => now()]);

        $segunda = DocumentoVersion::create([
            'tenant_id' => $notaria['notaria']->id,
            'documento_id' => $documento->id,
            'version' => 2,
            'ruta_archivo' => 'ejemplo/segunda.pdf',
            'nombre_archivo' => 'segunda.pdf',
            'vigente_hasta' => null,
        ]);

        $documento->update(['version_actual' => 2]);

        // La primera SIGUE EXISTIENDO. Solo dejo de estar vigente.
        $this->assertNotNull(DocumentoVersion::find($primera->id));
        $this->assertFalse($primera->refresh()->estaVigente());
        $this->assertTrue($segunda->refresh()->estaVigente());
        $this->assertCount(2, DocumentoVersion::query()->where('documento_id', $documento->id)->get());
    }

    public function test_marcar_como_reemplazado_conserva_el_documento(): void
    {
        $notaria = $this->crearNotaria();
        $expediente = $this->expediente($notaria);

        $documento = Documento::factory()->create([
            'tenant_id' => $notaria['notaria']->id,
            'expediente_id' => $expediente->id,
        ]);

        $documento->update([
            'estado' => 'reemplazado',
            'reemplazado_en' => now(),
            'reemplazado_por' => $notaria['notario']->id,
        ]);

        // Sigue ahi, con su estado y su fecha.
        $recuperado = Documento::find($documento->id);

        $this->assertNotNull($recuperado);
        $this->assertSame('reemplazado', $recuperado->estado->value);
        $this->assertNotNull($recuperado->reemplazado_en);
    }

    /**
     * La API NO EXPONE NINGUNA RUTA DELETE DE DOCUMENTOS.
     *
     * Esta prueba no prueba el comportamiento: prueba la AUSENCIA de una via.
     * Es una prueba de seguridad por omision, y hay que mantenerla.
     */
    public function test_la_api_no_tiene_ninguna_ruta_delete_de_documentos(): void
    {
        $rutas = collect(app('router')->getRoutes())->map(
            fn ($ruta): string => implode(' ', $ruta->methods())
        );

        $deleteDeDocumentos = collect(app('router')->getRoutes())
            ->filter(fn ($ruta): bool => str_contains($ruta->uri(), 'documento'))
            ->filter(fn ($ruta): bool => in_array('DELETE', $ruta->methods(), true));

        $this->assertCount(
            0,
            $deleteDeDocumentos,
            'Se agrego una ruta DELETE de documentos. Un documento de un expediente '
            . 'no se borra: se reemplaza. Si necesitas borrar, el motivo tiene que '
            . 'discutirse antes, no despues de escribir el codigo.'
        );

        $this->assertTrue($rutas->isNotEmpty());
    }

    /**
     * Tampoco hay ruta DELETE de expedientes: uno mal abierto se ANULA.
     */
    public function test_la_api_no_tiene_ninguna_ruta_delete_de_expedientes(): void
    {
        $deleteDeExpedientes = collect(app('router')->getRoutes())
            ->filter(fn ($ruta): bool => str_contains($ruta->uri(), 'expediente'))
            ->filter(fn ($ruta): bool => in_array('DELETE', $ruta->methods(), true));

        $this->assertCount(0, $deleteDeExpedientes);
    }

    /**
     * El listado de documentos devuelve el historial COMPLETO, incluyendo los
     * reemplazados. Si la API los ocultara, la interfaz no tendria forma de
     * mostrar que existieron.
     */
    public function test_el_listado_incluye_los_documentos_reemplazados(): void
    {
        $notaria = $this->crearNotaria();
        $expediente = $this->expediente($notaria);

        Documento::factory()->create([
            'tenant_id' => $notaria['notaria']->id,
            'expediente_id' => $expediente->id,
            'nombre' => 'Contrato vigente',
            'estado' => 'vigente',
        ]);

        Documento::factory()->reemplazado()->create([
            'tenant_id' => $notaria['notaria']->id,
            'expediente_id' => $expediente->id,
            'nombre' => 'Contrato anterior',
        ]);

        $this->comoUsuario($notaria['administrador']);

        $respuesta = $this->getJson("/api/v1/expedientes/{$expediente->id}/documentos");

        $respuesta->assertOk();
        $respuesta->assertJsonCount(2, 'datos');
        $respuesta->assertJsonPath('vigentes', 1);
        $respuesta->assertJsonPath('reemplazados', 1);
    }

    /** @param array{notaria: \App\Models\Notaria, tipo: \App\Models\TipoOperacion} $notaria */
    private function expediente(array $notaria): Expediente
    {
        return Expediente::factory()->create([
            'tenant_id' => $notaria['notaria']->id,
            'tipo_operacion_id' => $notaria['tipo']->id,
        ]);
    }
}