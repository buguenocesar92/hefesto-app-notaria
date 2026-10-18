<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\EstadoEtapa;
use App\Enums\NivelSemaforo;
use App\Services\Semaforo\SemaforoEtapa;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * EL SEMAFORO, SIN BASE DE DATOS.
 *
 * Son las pruebas mas importantes del producto, y tambien las mas baratas:
 * `SemaforoEtapa` es un objeto de valor puro, sin Eloquent y sin SQL.
 *
 * SI SE PUEDEN EJECUTAR SIN NADA MAS
 * -----------------------------------
 * Estas pruebas NO necesitan PostgreSQL, ni Docker, ni la base de datos. Se
 * pueden correr apenas se tenga PHP:
 *
 *   vendor/bin/phpunit --testsuite=Unit
 *
 * Esa es la razon de que la regla del semaforo este aislada en una clase sin
 * dependencias: la regla que decide que esta atrasado se puede comprobar en
 * cualquier maquina, sin levantar nada.
 *
 * LA REGLA QUE SE PRUEBA, EN UNA FRASE
 * ------------------------------------
 * "Una etapa se marca SOLO si la notaria	configuro un umbral. Sin umbral, no
 * se marca nada." Todo lo demas son los bordes de esa regla.
 */
class SemaforoEtapaTest extends TestCase
{
    private CarbonImmutable $ahora;

    protected function setUp(): void
    {
        parent::setUp();

        // Una hora fija: las pruebas no dependen del reloj del sistema.
        $this->ahora = CarbonImmutable::parse('2026-10-17 12:00:00');
    }

    public function test_sin_umbral_configurado_no_se_marca_nada(): void
    {
        // 500 dias esperando, y aun asi no se marca. Este es el caso que
        // protege a la notaria de un numero inventado.
        $semaforo = SemaforoEtapa::calcular(
            estado: EstadoEtapa::EnCurso,
            diasAlerta: null,
            diasCritico: null,
            referencia: $this->ahora->subDays(500),
            ahora: $this->ahora,
        );

        $this->assertSame(NivelSemaforo::SinUmbral, $semaforo->nivel);
        $this->assertFalse($semaforo->estaAtrasada());
    }

    public function test_dentro_del_umbral_es_verde(): void
    {
        $semaforo = SemaforoEtapa::calcular(
            estado: EstadoEtapa::EnCurso,
            diasAlerta: 7,
            diasCritico: 14,
            referencia: $this->ahora->subDays(3),
            ahora: $this->ahora,
        );

        $this->assertSame(NivelSemaforo::Verde, $semaforo->nivel);
        $this->assertFalse($semaforo->estaAtrasada());
        $this->assertSame(3, $semaforo->diasEnEstado);
    }

    public function test_al_llegar_al_umbral_pasa_a_amarillo(): void
    {
        $semaforo = SemaforoEtapa::calcular(
            estado: EstadoEtapa::EnCurso,
            diasAlerta: 7,
            diasCritico: 14,
            referencia: $this->ahora->subDays(7),
            ahora: $this->ahora,
        );

        $this->assertSame(NivelSemaforo::Amarillo, $semaforo->nivel);
        $this->assertTrue($semaforo->estaAtrasada());
    }

    public function test_al_llegar_al_critico_pasa_a_rojo(): void
    {
        $semaforo = SemaforoEtapa::calcular(
            estado: EstadoEtapa::EnCurso,
            diasAlerta: 7,
            diasCritico: 14,
            referencia: $this->ahora->subDays(14),
            ahora: $this->ahora,
        );

        $this->assertSame(NivelSemaforo::Rojo, $semaforo->nivel);
        $this->assertTrue($semaforo->estaAtrasada());
    }

    /**
     * Sin umbral critico aparte, NO hay rojo.
     *
     * Un rojo por omision seria inventar un segundo plazo que nadie
     * configuro.
     */
    public function test_sin_umbral_critico_no_hay_rojo_nunca(): void
    {
        $semaforo = SemaforoEtapa::calcular(
            estado: EstadoEtapa::EnCurso,
            diasAlerta: 7,
            diasCritico: null,
            referencia: $this->ahora->subDays(999),
            ahora: $this->ahora,
        );

        $this->assertSame(NivelSemaforo::Amarillo, $semaforo->nivel);
        $this->assertNull($semaforo->diasCritico);
    }

    /**
     * Una etapa terminada hace tres semanas NO es un atraso.
     *
     * Marcar en rojo algo que la notaria ya resolvio seria mentirle.
     */
    public function test_una_etapa_terminada_nunca_se_marca_como_atrasada(): void
    {
        $semaforo = SemaforoEtapa::calcular(
            estado: EstadoEtapa::Terminada,
            diasAlerta: 7,
            diasCritico: 14,
            referencia: $this->ahora->subDays(90),
            ahora: $this->ahora,
        );

        $this->assertSame(NivelSemaforo::Terminado, $semaforo->nivel);
        $this->assertFalse($semaforo->estaAtrasada());
    }

    public function test_una_etapa_omitida_tampoco_se_marca(): void
    {
        $semaforo = SemaforoEtapa::calcular(
            estado: EstadoEtapa::Omitida,
            diasAlerta: 7,
            diasCritico: 14,
            referencia: $this->ahora->subDays(90),
            ahora: $this->ahora,
        );

        $this->assertSame(NivelSemaforo::Terminado, $semaforo->nivel);
    }

    /**
     * Sin fecha de referencia no se inventa una: se cuentan cero dias.
     */
    public function test_sin_fecha_de_referencia_no_se_inventan_dias(): void
    {
        $semaforo = SemaforoEtapa::calcular(
            estado: EstadoEtapa::Pendiente,
            diasAlerta: 7,
            diasCritico: 14,
            referencia: null,
            ahora: $this->ahora,
        );

        $this->assertSame(0, $semaforo->diasEnEstado);
        $this->assertSame(NivelSemaforo::Verde, $semaforo->nivel);
    }

    /**
     * Dias COMPLETOS, no redondeados: una etapa de tres horas no esta en un
     * dia.
     */
    public function test_los_dias_son_completos_no_redondeados(): void
    {
        $semaforo = SemaforoEtapa::calcular(
            estado: EstadoEtapa::EnCurso,
            diasAlerta: 7,
            diasCritico: 14,
            referencia: $this->ahora->subHours(23),
            ahora: $this->ahora,
        );

        $this->assertSame(0, $semaforo->diasEnEstado);
    }

    /**
     * Un reloj desfasado, o una fecha mal digitada en el futuro, no puede
     * producir dias negativos.
     */
    public function test_una_fecha_en_el_futuro_no_produce_dias_negativos(): void
    {
        $semaforo = SemaforoEtapa::calcular(
            estado: EstadoEtapa::EnCurso,
            diasAlerta: 7,
            diasCritico: 14,
            referencia: $this->ahora->addDays(10),
            ahora: $this->ahora,
        );

        $this->assertSame(0, $semaforo->diasEnEstado);
        $this->assertSame(NivelSemaforo::Verde, $semaforo->nivel);
    }

    /**
     * Si por error el critico quedo menor que la alerta, manda la alerta: una
     * etapa nunca puede estar en rojo sin haber pasado antes por amarillo.
     */
    public function test_un_critico_menor_que_la_alerta_no_produce_rojo_sorpresivo(): void
    {
        $semaforo = SemaforoEtapa::calcular(
            estado: EstadoEtapa::EnCurso,
            diasAlerta: 7,
            diasCritico: 3,
            referencia: $this->ahora->subDays(5),
            ahora: $this->ahora,
        );

        $this->assertSame(NivelSemaforo::Verde, $semaforo->nivel);

        $semaforoTardio = SemaforoEtapa::calcular(
            estado: EstadoEtapa::EnCurso,
            diasAlerta: 7,
            diasCritico: 3,
            referencia: $this->ahora->subDays(9),
            ahora: $this->ahora,
        );

        $this->assertSame(NivelSemaforo::Amarillo, $semaforoTardio->nivel);
    }

    /**
     * El texto que se le muestra a una persona de la notaria dice "lleva N
     * dias", que es un hecho, y NUNCA dice "se vencio" ni "vence en", que
     * serian afirmaciones legales que este sistema no puede hacer.
     */
    public function test_el_resumen_no_afirma_ningun_vencimiento(): void
    {
        $semaforo = SemaforoEtapa::calcular(
            estado: EstadoEtapa::EnCurso,
            diasAlerta: 7,
            diasCritico: 14,
            referencia: $this->ahora->subDays(20),
            ahora: $this->ahora,
        );

        $resumen = mb_strtolower($semaforo->resumen());

        $this->assertStringContainsString('lleva 20 dias', $resumen);
        $this->assertStringNotContainsString('venci', $resumen);
        $this->assertStringNotContainsString('plazo legal', $resumen);
    }

    public function test_sin_umbral_el_resumen_explica_que_no_se_marca_y_por_que(): void
    {
        $semaforo = SemaforoEtapa::calcular(
            estado: EstadoEtapa::EnCurso,
            diasAlerta: null,
            diasCritico: null,
            referencia: $this->ahora->subDays(20),
            ahora: $this->ahora,
        );

        $resumen = mb_strtolower($semaforo->resumen());

        $this->assertStringContainsString('umbral configurado', $resumen);
        $this->assertStringContainsString('no hay ningun plazo legal', $resumen);
    }
}