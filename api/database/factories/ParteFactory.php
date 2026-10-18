<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Parte;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Parte>
 */
class ParteFactory extends Factory
{
    protected $model = Parte::class;

    /**
     * SIN ROL, a proposito.
     *
     * El rol no es un atributo de la persona: es de la relacion con el
     * expediente, y vive en `expediente_partes`. Una factory que le pusiera un
     * rol a la parte estaria modelando mal el dominio.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => \App\Models\Notaria::factory(),
            'nombre' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'rut' => fake()->unique()->numerify('##.###.###-#'),
            'correo' => fake()->safeEmail(),
            'telefono' => '+56 9 ' . fake()->numerify('#### ####'),
            'direccion' => fake()->address(),
            'observacion' => null,
        ];
    }
}