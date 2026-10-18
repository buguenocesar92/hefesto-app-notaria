<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Notaria;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Notaria>
 */
class NotariaFactory extends Factory
{
    protected $model = Notaria::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $nombre = fake()->unique()->company() . ' Notaria';

        return [
            'id' => (string) Str::uuid(),
            'slug' => Str::slug($nombre) . '-' . Str::lower(Str::random(4)),
            'nombre' => $nombre,
            'rut' => fake()->numerify('##.###.###-#'),
            'comuna' => fake()->randomElement(['Providencia', 'Las Condes', 'Centro', 'Ñuñoa']),
            'direccion' => fake()->address(),
            'correo_contacto' => fake()->safeEmail(),
            'telefono_contacto' => '+56 2 2' . fake()->numerify('### ####'),
            'activo' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}