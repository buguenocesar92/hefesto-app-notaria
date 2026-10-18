<?php

declare(strict_types=1);

namespace Tests\Feature\Aislamiento;

use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Parte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QUE LA API RESPONDA 404, Y NO 403, CUANDO EL RECURSO ES DE OTRA NOTARIA.
 *
 * Un 403 confirmaria que el recurso existe. En una notaria, eso ya es filtrar
 * informacion: le diria a un intruso que hay un expediente con ese
 * identificador. El 404 no dice nada.
 *
 * La RLS hace que el recurso de verdad no exista a ojos de la otra notaria, y
 * estas pruebas comprueban que la respuesta sea coherente con eso.
 */
class AislamientoEnEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_se_puede_ver_un_expediente_de_otra_notaria(): void
    {
        $primera = $this->crearNotaria();
        $segunda = $this->crearSegundaNotaria();

        $ajeno = Expediente::factory()->create([
            'tenant_id' => $segunda['notaria']->id,
            'tipo_operacion_id' => $segunda['tipo']->id,
        ]);

        // Sesión de la primera notaria, identificador de la segunda.
        $this->comoUsuario($primera['administrador']);

        $this->getJson("/api/v1/expedientes/{$ajeno->id}")
            ->assertNotFound();
    }

    public function test_no_se_puede_cerrar_un_expediente_de_otra_notaria(): void
    {
        $primera = $this->crearNotaria();
        $segunda = $this->crearSegundaNotaria();

        $ajeno = Expediente::factory()->create([
            'tenant_id' => $segunda['notaria']->id,
            'tipo_operacion_id' => $segunda['tipo']->id,
        ]);

        $this->comoUsuario($primera['administrador']);

        $this->postJson("/api/v1/expedientes/{$ajeno->id}/cerrar")
            ->assertNotFound();

        // Y el expediente sigue abierto: no se toco nada.
        $this->fijarTenant($segunda['notaria']->id);
        $this->assertSame('en_tramite', Expediente::find($ajeno->id)->estado->value);
    }

    public function test_no_se_pueden_ver_los_documentos_de_otra_notaria(): void
    {
        $primera = $this->crearNotaria();
        $segunda = $this->crearSegundaNotaria();

        $expedienteAjeno = Expediente::factory()->create([
            'tenant_id' => $segunda['notaria']->id,
            'tipo_operacion_id' => $segunda['tipo']->id,
        ]);

        Documento::factory()->create([
            'tenant_id' => $segunda['notaria']->id,
            'expediente_id' => $expedienteAjeno->id,
        ]);

        $this->comoUsuario($primera['administrador']);

        $this->getJson("/api/v1/expedientes/{$expedienteAjeno->id}/documentos")
            ->assertNotFound();
    }

    public function test_el_listado_de_expedientes_no_trae_expedientes_de_otra_notaria(): void
    {
        $primera = $this->crearNotaria();
        Expediente::factory()->count(2)->create([
            'tenant_id' => $primera['notaria']->id,
            'tipo_operacion_id' => $primera['tipo']->id,
        ]);

        $segunda = $this->crearSegundaNotaria();
        Expediente::factory()->count(4)->create([
            'tenant_id' => $segunda['notaria']->id,
            'tipo_operacion_id' => $segunda['tipo']->id,
        ]);

        $this->comoUsuario($primera['administrador']);

        $respuesta = $this->getJson('/api/v1/expedientes');

        $respuesta->assertOk();
        $respuesta->assertJsonCount(2, 'data');
        $respuesta->assertJsonPath('total', 2);
    }

    public function test_el_listado_de_partes_no_trae_partes_de_otra_notaria(): void
    {
        $primera = $this->crearNotaria();
        Parte::factory()->count(2)->create(['tenant_id' => $primera['notaria']->id]);

        $segunda = $this->crearSegundaNotaria();
        Parte::factory()->count(3)->create(['tenant_id' => $segunda['notaria']->id]);

        $this->comoUsuario($primera['administrador']);

        $respuesta = $this->getJson('/api/v1/partes');

        $respuesta->assertOk();
        $respuesta->assertJsonCount(2, 'data');
    }

    /**
     * El tablero tampoco: el conteo de expedientes abiertos de una notaria no
     * puede incluir los de la otra.
     */
    public function test_el_tablero_no_cuenta_lo_de_otra_notaria(): void
    {
        $primera = $this->crearNotaria();

        \App\Models\ConfiguracionNotaria::create([
            'tenant_id' => $primera['notaria']->id,
            'dias_alerta_por_defecto' => 7,
            'dias_critico_por_defecto' => 14,
        ]);

        Expediente::factory()->count(2)->create([
            'tenant_id' => $primera['notaria']->id,
            'tipo_operacion_id' => $primera['tipo']->id,
        ]);

        $segunda = $this->crearSegundaNotaria();
        Expediente::factory()->count(5)->create([
            'tenant_id' => $segunda['notaria']->id,
            'tipo_operacion_id' => $segunda['tipo']->id,
        ]);

        $this->comoUsuario($primera['administrador']);

        $respuesta = $this->getJson('/api/v1/tablero');

        $respuesta->assertOk();
        $respuesta->assertJsonPath('expedientes_abiertos', 2);
    }
}