<?php

declare(strict_types=1);

namespace App\Services\Expedientes;

use App\Enums\EstadoExpediente;
use App\Models\ConfiguracionNotaria;
use App\Models\Expediente;
use App\Models\TipoOperacion;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Apertura de un expediente: crea el expediente y sus etapas.
 *
 * COPIA LAS ETAPAS DE LA PLANTILLA, Y NO LAS IMPONE
 * --------------------------------------------------
 * Al abrir un expediente se copian las etapas que la notaria definio para ese
 * tipo de operacion. El sistema no agrega etapas, no las reordena y no
 * inventa ninguna: si la plantilla esta vacia, el expediente se abre sin
 * etapas y la notaria las agrega a mano.
 *
 * Los UMBRALES tambien se copian, en cascada:
 *   1. el umbral de la plantilla de la etapa, si tiene;
 *   2. si no, el umbral por defecto del tipo de operacion;
 *   3. si no, el umbral por defecto de la notaria;
 *   4. si no hay ninguno, queda NULL, y el semaforo NO marca esa etapa.
 *
 * Copiarlos y no heredarlos en caliente es deliberado: si la notaria cambia
 * su umbral por defecto manana, los expedientes abiertos hoy conservan el
 * criterio con el que se estan midiendo, y el tablero no salta de color a
 * todo el mundo de golpe.
 *
 * EL NUMERO DE PROTOCOLO LO PONE LA NOTARIA
 * ------------------------------------------
 * Este servicio no lo genera. Lo unico que genera es `folio`, que es un
 * identificador interno de esta aplicacion y no sale de ella.
 */
class AperturadorDeExpediente
{
    public function __construct(private readonly ContextoTenant $contexto) {}

    /**
     * @param  array{numero_protocolo?: ?string, descripcion?: ?string, profesional_id?: ?string, usuario_responsable_id?: ?string, fecha_inicio?: ?string}  $datos
     */
    public function abrir(string $tenantId, TipoOperacion $tipoOperacion, array $datos): Expediente
    {
        return $this->contexto->ejecutarAislado($tenantId, function () use ($tenantId, $tipoOperacion, $datos): Expediente {
            return DB::transaction(function () use ($tenantId, $tipoOperacion, $datos): Expediente {
                $folio = $this->siguienteFolio($tenantId);

                $expediente = Expediente::create([
                    'tenant_id' => $tenantId,
                    'folio' => $folio,
                    // El numero de protocolo lo escribe la notaria. Puede ir
                    // vacio al abrir: es normal que todavia no exista.
                    'numero_protocolo' => $datos['numero_protocolo'] ?? null,
                    'tipo_operacion_id' => $tipoOperacion->id,
                    'profesional_responsable_id' => $datos['profesional_id'] ?? null,
                    'usuario_responsable_id' => $datos['usuario_responsable_id'] ?? null,
                    'estado' => EstadoExpediente::EnTramite,
                    'descripcion' => $datos['descripcion'] ?? null,
                    'fecha_inicio' => $datos['fecha_inicio'] ?? now()->toDateString(),
                    'dias_alerta_por_defecto' => $tipoOperacion->dias_alerta_por_defecto,
                    'dias_critico_por_defecto' => $tipoOperacion->dias_critico_por_defecto,
                ]);

                $this->copiarEtapas($expediente, $tipoOperacion);

                return $expediente->refresh();
            });
        });
    }

    /**
     * Copia las etapas de la plantilla, resolviendo los umbrales en cascada.
     */
    private function copiarEtapas(Expediente $expediente, TipoOperacion $tipoOperacion): void
    {
        $porDefectoNotaria = $this->umbralesPorDefectoDeLaNotaria($expediente->tenant_id);

        foreach ($tipoOperacion->etapasPlantilla as $plantilla) {
            $expediente->etapas()->create([
                'tenant_id' => $expediente->tenant_id,
                'etapa_plantilla_id' => $plantilla->id,
                'orden' => $plantilla->orden,
                'nombre' => $plantilla->nombre,
                'descripcion' => $plantilla->descripcion,
                'estado' => 'pendiente',
                'requiere_antecedente' => $plantilla->requiere_antecedente,
                'dias_alerta' => $plantilla->dias_alerta
                    ?? $tipoOperacion->dias_alerta_por_defecto
                    ?? $porDefectoNotaria[0],
                'dias_critico' => $plantilla->dias_critico
                    ?? $tipoOperacion->dias_critico_por_defecto
                    ?? $porDefectoNotaria[1],
            ]);
        }
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function umbralesPorDefectoDeLaNotaria(string $tenantId): array
    {
        $configuracion = ConfiguracionNotaria::query()
            ->where('tenant_id', $tenantId)
            ->first();

        return [
            $configuracion?->dias_alerta_por_defecto,
            $configuracion?->dias_critico_por_defecto,
        ];
    }

    /**
     * Folio interno, incremental y atomico.
     *
     * `UPDATE ... RETURNING` dentro de la misma transaccion que inserta el
     * expediente: dos aperturas simultaneas no pueden sacar el mismo numero.
     * Y no es un numero de protocolo: no sale de esta aplicacion.
     */
    private function siguienteFolio(string $tenantId): string
    {
        $existe = DB::table('contadores')
            ->where('tenant_id', $tenantId)
            ->where('clave', 'expediente')
            ->exists();

        if (! $existe) {
            try {
                DB::table('contadores')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'clave' => 'expediente',
                    'valor' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 'EXP-00001';
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // Otra peticion lo creo primero. Se cae al incremento normal.
            }
        }

        $valor = DB::table('contadores')
            ->where('tenant_id', $tenantId)
            ->where('clave', 'expediente')
            ->increment('valor', 1, [
                'updated_at' => now(),
            ]);

        return 'EXP-' . str_pad((string) $valor, 5, '0', STR_PAD_LEFT);
    }
}