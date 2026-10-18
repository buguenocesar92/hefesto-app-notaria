<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TipoOperacion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TipoOperacion>
 */
class TipoOperacionFactory extends Factory
{
    protected $model = TipoOperacion::class;

    /**
     * Los umbrales por defecto NACEN EN NULL a proposito.
     *
     * Una factory que inventara un numero de dias estaria sembrando el
     * problema que el producto evita: un umbral que nadie eligio. El que
     * escribe la prueba que necesita un umbral, lo escribe a mano.
     */
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => \App\Models\Notaria::factory(),
            'codigo' => 'OP-' . fake()->unique()->bothify('??##'),
            'nombre' => fake()->words(2, true),
            'descripcion' => null,
            'color' => fake()->randomElement(['#2563eb', '#059669', '#d97706', '#7c3aed']),
            'duracion_atencion_minutos' => fake()->randomElement([30, 45, 60, 90]),
            'dias_alerta_por_defecto' => null,
            'dias_critico_por_defecto' => null,
            'activo' => true,
        ];
    }

    public function conUmbral(int $alerta, ?int $critico = null): static
    {
        return $this->state(fn (): array => [
            'dias_alerta_por_defecto' => $alerta,
            'dias_critico_por_defecto' => $critico,
        ]);
    }
}