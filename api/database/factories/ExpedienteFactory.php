<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Expediente;
use App\Models\TipoOperacion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Expediente>
 */
class ExpedienteFactory extends Factory
{
    protected $model = Expediente::class;

    /**
     * `numero_protocolo` se deja en NULL a proposito en la factory.
     *
     * El numero de protocolo lo pone la notaria, no el sistema. Una factory
     * que lo inventara seria exactamente el error que el producto evita: un
     * identificador con apariencia oficial generado por software.
     */
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => \App\Models\Notaria::factory(),
            'folio' => 'EXP-' . fake()->unique()->numerify('#####'),
            'numero_protocolo' => null,
            'tipo_operacion_id' => TipoOperacion::factory(),
            'profesional_responsable_id' => null,
            'usuario_responsable_id' => null,
            'estado' => \App\Enums\EstadoExpediente::EnTramite,
            'descripcion' => fake()->sentence(4),
            'detalle' => null,
            'fecha_inicio' => now()->subDays(fake()->numberBetween(1, 30))->toDateString(),
            'fecha_cierre' => null,
            'dias_alerta_por_defecto' => null,
            'dias_critico_por_defecto' => null,
        ];
    }

    public function cerrado(): static
    {
        return $this->state(fn (): array => [
            'estado' => \App\Enums\EstadoExpediente::Cerrado,
            'fecha_cierre' => now()->toDateString(),
        ]);
    }
}