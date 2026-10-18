<?php

declare(strict_types=1);

namespace Tests\Feature\Aislamiento;

use App\Models\Antecedente;
use App\Models\Documento;
use App\Models\Etapa;
use App\Models\Expediente;
use App\Models\Parte;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PRUEBAS CRITICAS: una notaria no puede leer los datos de otra.
 *
 * En una notaria esto no es un problema comercial: son datos de patrimonio,
 * familia y sucesiones de personas identificables, protegidos por la Ley 19.628.
 *
 * Estas pruebas van contra PostgreSQL de verdad, porque el aislamiento esta
 * implementado con Row Level Security. Si se corren contra SQLite, pasan sin
 * comprobar nada. Ver phpunit.xml.
 *
 * Lo que se comprueba aca:
 *
 *  1. Sin contexto de notaria, las tablas devuelven un ERROR, no filas.
 *  2. Con el contexto de otra notaria, una consulta devuelve SOLO sus filas.
 *  3. Un `id` de otra notaria no existe para esta persona (404, no 403).
 *  4. Un expediente NO ve los documentos de otro expediente.
 *  5. No se puede escribir una fila en nombre de otra notaria.
 *  6. El contexto de notaria no sobrevive a la transaccion.
 */
class AislamientoDeDatosTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_contexto_de_notaria_la_consulta_falla_en_vez_de_traer_datos(): void
    {
        $primera = $this->crearNotaria();
        Expediente::factory()->create(['tenant_id' => $primera['notaria']->id]);

        // Se limpia el contexto a proposito: es la situacion de una consulta
        // que se escapa del middleware.
        //
        // Lo que se espera NO es "cero filas", sino un ERROR. `app_tenant_id()`
        // lanza excepcion cuando no hay contexto, y la politica RLS aborta la
        // consulta. Devolver cero filas seria mas comodo de probar, pero
        // dejaria pasar el caso peligroso: que alguien "arregle" la funcion
        // devolviendo NULL y termine leyendo filas de la notaria equivocada sin
        // que nada lo delate. Ahi el aislamiento falla abierto.
        DB::select('SELECT set_config(?, ?, false)', [ContextoTenant::VARIABLE, '']);

        $this->expectException(QueryException::class);

        Expediente::query()->count();
    }

    public function test_cada_notaria_solo_ve_sus_propios_expedientes(): void
    {
        $primera = $this->crearNotaria();
        Expediente::factory()->count(3)->create(['tenant_id' => $primera['notaria']->id]);

        $segunda = $this->crearSegundaNotaria();
        Expediente::factory()->count(2)->create(['tenant_id' => $segunda['notaria']->id]);

        $this->fijarTenant($primera['notaria']->id);
        $this->assertSame(3, Expediente::query()->count());
        $this->assertSame($primera['notaria']->id, Expediente::query()->value('tenant_id'));

        $this->fijarTenant($segunda['notaria']->id);
        $this->assertSame(2, Expediente::query()->count());
        $this->assertSame($segunda['notaria']->id, Expediente::query()->value('tenant_id'));
    }

    public function test_el_expediente_de_otra_notaria_no_se_puede_leer_ni_escribir(): void
    {
        $primera = $this->crearNotaria();
        $segunda = $this->crearSegundaNotaria();

        $expedienteAjeno = Expediente::factory()->create([
            'tenant_id' => $segunda['notaria']->id,
            'tipo_operacion_id' => $segunda['tipo']->id,
        ]);

        // Volvemos al contexto de la primera notaria.
        $this->fijarTenant($primera['notaria']->id);

        $this->assertNull(Expediente::find($expedienteAjeno->id));

        // Y tampoco se puede tocar: la politica tiene WITH CHECK.
        $this->expectException(QueryException::class);

        DB::table('expedientes')
            ->where('id', $expedienteAjeno->id)
            ->update(['descripcion' => 'secuestrado']);
    }

    /**
     * EL CASO QUE DA NIEGO A ESTE PRODUCTO.
     *
     * Un expediente de la notaria A no puede ver los documentos de un
     * expediente de la notaria B. Si esto fallara, una notaria veria los
     * documentos de otra notaria: datos de patrimonio y de personas
     * identificables.
     */
    public function test_un_expediente_no_ve_los_documentos_de_otra_notaria(): void
    {
        $primera = $this->crearNotaria();
        $expedientePropio = Expediente::factory()->create([
            'tenant_id' => $primera['notaria']->id,
            'tipo_operacion_id' => $primera['tipo']->id,
        ]);
        Documento::factory()->count(2)->create([
            'tenant_id' => $primera['notaria']->id,
            'expediente_id' => $expedientePropio->id,
        ]);

        $segunda = $this->crearSegundaNotaria();
        $expedienteAjeno = Expediente::factory()->create([
            'tenant_id' => $segunda['notaria']->id,
            'tipo_operacion_id' => $segunda['tipo']->id,
        ]);
        $documentoAjeno = Documento::factory()->create([
            'tenant_id' => $segunda['notaria']->id,
            'expediente_id' => $expedienteAjeno->id,
        ]);

        $this->fijarTenant($primera['notaria']->id);

        // No se ve el documento ajeno.
        $this->assertNull(Documento::find($documentoAjeno->id));

        // Y el expediente propio solo muestra SUS documentos: la relacion de
        // Eloquent tambien pasa por la politica.
        $visibles = $expedientePropio->documentos()->pluck('id');

        $this->assertCount(2, $visibles);
        $this->assertFalse($visibles->contains($documentoAjeno->id));
    }

    public function test_no_se_puede_insertar_una_fila_con_el_tenant_de_otra_notaria(): void
    {
        $primera = $this->crearNotaria();
        $segunda = $this->crearSegundaNotaria();

        // Contexto de la primera notaria, pero se intenta guardar un
        // expediente de la segunda. La politica WITH CHECK lo impide.
        $this->fijarTenant($primera['notaria']->id);

        $this->expectException(QueryException::class);

        Expediente::query()->create([
            'tenant_id' => $segunda['notaria']->id,
            'folio' => 'EXP-AJENO',
            'tipo_operacion_id' => $primera['tipo']->id,
            'estado' => 'en_tramite',
            'fecha_inicio' => now()->toDateString(),
        ]);
    }

    public function test_las_partes_de_otra_notaria_no_se_ven(): void
    {
        $primera = $this->crearNotaria();
        Parte::factory()->count(2)->create(['tenant_id' => $primera['notaria']->id]);

        $segunda = $this->crearSegundaNotaria();
        Parte::factory()->count(3)->create(['tenant_id' => $segunda['notaria']->id]);

        $this->fijarTenant($primera['notaria']->id);
        $this->assertSame(2, Parte::query()->count());

        $this->fijarTenant($segunda['notaria']->id);
        $this->assertSame(3, Parte::query()->count());
    }

    public function test_las_etapas_y_antecedentes_de_otra_notaria_no_se_ven(): void
    {
        $primera = $this->crearNotaria();
        $expediente = Expediente::factory()->create([
            'tenant_id' => $primera['notaria']->id,
            'tipo_operacion_id' => $primera['tipo']->id,
        ]);
        Etapa::factory()->create([
            'tenant_id' => $primera['notaria']->id,
            'expediente_id' => $expediente->id,
        ]);
        Antecedente::factory()->create([
            'tenant_id' => $primera['notaria']->id,
            'expediente_id' => $expediente->id,
        ]);

        $this->fijarTenant('11111111-1111-4111-8111-111111111111');

        $this->assertSame(0, Etapa::query()->count());
        $this->assertSame(0, Antecedente::query()->count());
    }

    public function test_el_contexto_de_notaria_no_sobrevive_a_la_transaccion(): void
    {
        $notaria = $this->crearNotaria();
        Expediente::factory()->create([
            'tenant_id' => $notaria['notaria']->id,
            'tipo_operacion_id' => $notaria['tipo']->id,
        ]);

        $contexto = new ContextoTenant();

        $conteo = $contexto->ejecutarAislado(
            $notaria['notaria']->id,
            fn (): int => Expediente::query()->count()
        );

        $this->assertSame(1, $conteo);
        $this->assertNull(
            $contexto->tenantIdEnConexion(),
            'La variable de contexto debe desaparecer al terminar la transaccion. '
            . 'Si queda, el worker de colas podria leer datos de otra notaria.'
        );
    }

    public function test_la_funcion_de_contexto_falla_cerrada_sin_notaria(): void
    {
        DB::select('SELECT set_config(?, ?, false)', [ContextoTenant::VARIABLE, '']);

        $this->expectException(QueryException::class);

        DB::select('SELECT public.app_tenant_id()');
    }
}