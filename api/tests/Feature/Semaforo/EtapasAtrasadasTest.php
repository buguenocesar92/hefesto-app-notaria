<?php

declare(strict_types=1);

namespace Tests\Feature\Semaforo;

use App\Enums\NivelSemaforo;
use App\Models\AlertaSemaforo;
use App\Models\ConfiguracionNotaria;
use App\Models\Etapa;
use App\Models\Expediente;
use App\Services\Semaforo\RevisorDeSemaforo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QUE UNA ETAPA ATRASADA SE MARQUE SOLA.
 *
 * Esta es la prueba que demuestra el producto: no hace falta que alguien
 * marque nada. Basta con que la etapa siga esperando y el sistema lo note.
 *
 * Corre contra PostgreSQL de verdad. Ver docs/10.
 */
class EtapasAtrasadasTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_etapa_que_supera_el_umbral_se_marca_sola(): void
    {
        $notaria = $this->crearNotaria();
        $etapa = $this->etapaEsperando($notaria, dias: 20, alerta: 7, critico: 14);

        (new RevisorDeSemaforo())->revisar();

        $etapa->refresh();

        $this->assertSame(NivelSemaforo::Rojo, $etapa->nivel_semaforo);
        $this->assertSame(20, $etapa->dias_en_estado);
        $this->assertNotNull($etapa->semaforo_calculado_en);
    }

    public function test_una_etapa_dentro_del_umbral_queda_en_verde(): void
    {
        $notaria = $this->crearNotaria();
        $etapa = $this->etapaEsperando($notaria, dias: 3, alerta: 7, critico: 14);

        (new RevisorDeSemaforo())->revisar();

        $this->assertSame(NivelSemaforo::Verde, $etapa->refresh()->nivel_semaforo);
        $this->assertSame(0, AlertaSemaforo::query()->count());
    }

    /**
     * SIN UMBRAL, NO SE MARCA NADA.
     *
     * Es el caso que mas importa: la etapa lleva 300 dias esperando y el
     * sistema NO la marca, porque la notaria no configuro ningun umbral.
     * Preferimos un tablero con huecos a un tablero con numeros falsos.
     */
    public function test_sin_umbral_no_se_marca_ni_se_abre_alerta(): void
    {
        $notaria = $this->crearNotaria();
        $etapa = $this->etapaEsperando($notaria, dias: 300, alerta: null, critico: null);

        (new RevisorDeSemaforo())->revisar();

        $etapa->refresh();

        $this->assertSame(NivelSemaforo::SinUmbral, $etapa->nivel_semaforo);
        $this->assertSame(0, AlertaSemaforo::query()->count());
    }

    public function test_correr_el_semaforo_dos_veces_no_duplica_la_alerta(): void
    {
        $notaria = $this->crearNotaria();
        $etapa = $this->etapaEsperando($notaria, dias: 20, alerta: 7, critico: 14);

        $revisor = new RevisorDeSemaforo();
        $revisor->revisar();
        $revisor->revisar();
        $revisor->revisar();

        // Una sola alerta abierta por etapa y nivel.
        $this->assertSame(1, AlertaSemaforo::query()->abiertas()->count());

        $alerta = AlertaSemaforo::query()->first();
        $this->assertSame(NivelSemaforo::Rojo, $alerta->nivel);
        $this->assertSame(3, $alerta->veces_revisada);
    }

    /**
     * La alerta se CIERRA cuando la etapa deja de estar atrasada. Si
     * Columbara cerrada, el tablero mostraria un problema viejo como si fuera
     * de hoy.
     */
    public function test_la_alerta_se_cierra_cuando_la_etapa_se_termina(): void
    {
        $notaria = $this->crearNotaria();
        $etapa = $this->etapaEsperando($notaria, dias: 20, alerta: 7, critico: 14);

        $revisor = new RevisorDeSemaforo();
        $revisor->revisar();

        $this->assertSame(1, AlertaSemaforo::query()->abiertas()->count());

        // La etapa avanza.
        $etapa->update([
            'estado' => 'terminada',
            'terminada_en' => now(),
        ]);

        $revisor->revisar();

        $this->assertSame(0, AlertaSemaforo::query()->abiertas()->count());
        $this->assertSame(1, AlertaSemaforo::query()->count());
    }

    /**
     * Si la etapa vuelve a atrasarse DESPUES de haberse cerrado la alerta,
     * se abre una nueva: es un hecho distinto y merece su propio registro.
     */
    public function test_si_vuelve_a_atrasarse_se_abre_una_alerta_nueva(): void
    {
        $notaria = $this->crearNotaria();
        $etapa = $this->etapaEsperando($notaria, dias: 20, alerta: 7, critico: 14);

        $revisor = new RevisorDeSemaforo();
        $revisor->revisar();

        // Se resuelve.
        $etapa->update(['estado' => 'terminada', 'terminada_en' => now()]);
        $revisor->revisar();

        // Y vuelve a atrasarse.
        $etapa->update(['estado' => 'en_curso', 'terminada_en' => null]);
        $revisor->revisar();

        $this->assertSame(2, AlertaSemaforo::query()->count());
        $this->assertSame(1, AlertaSemaforo::query()->abiertas()->count());
    }

    public function test_el_tablero_no_marca_nada_si_la_notaria_no_configuro_umbral(): void
    {
        $notaria = $this->crearNotaria();

        ConfiguracionNotaria::create([
            'tenant_id' => $notaria['notaria']->id,
            'dias_alerta_por_defecto' => null,
            'dias_critico_por_defecto' => null,
        ]);

        $this->etapaEsperando($notaria, dias: 400, alerta: 7, critico: 14);

        $this->comoUsuario($notaria['administrador']);

        $respuesta = $this->getJson('/api/v1/tablero');

        $respuesta->assertOk();
        $respuesta->assertJsonPath('sin_umbral', true);
        $respuesta->assertJsonPath('items', []);
    }

    public function test_el_tablero_muestra_la_etapa_atrasada_cuando_hay_umbral(): void
    {
        $notaria = $this->crearNotaria();

        ConfiguracionNotaria::create([
            'tenant_id' => $notaria['notaria']->id,
            'dias_alerta_por_defecto' => 7,
            'dias_critico_por_defecto' => 14,
        ]);

        $this->etapaEsperando($notaria, dias: 20, alerta: 7, critico: 14);

        $this->comoUsuario($notaria['administrador']);

        $respuesta = $this->getJson('/api/v1/tablero');

        $respuesta->assertOk();
        $respuesta->assertJsonPath('sin_umbral', false);
        $respuesta->assertJsonPath('conteo.rojo', 1);
        $respuesta->assertJsonCount(1, 'items');
        $respuesta->assertJsonPath('items.0.nivel', 'rojo');
        $respuesta->assertJsonPath('items.0.dias_en_estado', 20);
    }

    /**
     * @param  array{notaria: \App\Models\Notaria, tipo: \App\Models\TipoOperacion}  $notaria
     */
    private function etapaEsperando(array $notaria, int $dias, ?int $alerta, ?int $critico): Etapa
    {
        $expediente = Expediente::factory()->create([
            'tenant_id' => $notaria['notaria']->id,
            'tipo_operacion_id' => $notaria['tipo']->id,
        ]);

        return Etapa::factory()->create([
            'tenant_id' => $notaria['notaria']->id,
            'expediente_id' => $expediente->id,
            'estado' => 'en_curso',
            'iniciada_en' => now()->subDays($dias),
            'dias_alerta' => $alerta,
            'dias_critico' => $critico,
        ]);
    }
}