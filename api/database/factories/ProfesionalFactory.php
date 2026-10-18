<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profesional;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Profesional>
 */
class ProfesionalFactory extends Factory
{
    protected $model = Profesional::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => \App\Models\Notaria::factory(),
            'usuario_id' => null,
            'nombre' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'cargo' => fake()->randomElement(['notario', 'protocolista', 'auxiliar']),
            'correo' => fake()->safeEmail(),
            'telefono' => '+56 9 ' . fake()->numerify('#### ####'),
            'duracion_atencion_minutos' => fake()->randomElement([30, 45, 60]),
            'activo' => true,
        ];
    }
}