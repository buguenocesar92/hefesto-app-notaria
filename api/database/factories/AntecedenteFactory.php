<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoAntecedente;
use App\Models\Antecedente;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Antecedente>
 */
class AntecedenteFactory extends Factory
{
    protected $model = Antecedente::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => \App\Models\Notaria::factory(),
            'expediente_id' => \App\Models\Expediente::factory(),
            'etapa_id' => null,
            'descripcion' => fake()->sentence(4),
            'solicitado_a' => fake()->company(),
            'estado' => EstadoAntecedente::Pendiente,
            'responsable_id' => null,
            'solicitado_en' => now()->subDays(fake()->numberBetween(1, 30))->toDateString(),
            'recibido_en' => null,
            'respuesta' => null,
        ];
    }

    public function recibido(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoAntecedente::Recibido,
            'recibido_en' => now()->toDateString(),
        ]);
    }
}