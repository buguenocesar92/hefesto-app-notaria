<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RolUsuario;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $nombre = fake()->firstName();
        $apellidos = fake()->lastName();

        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => Notaria::factory(),
            'authentik_sub' => (string) Str::uuid(),
            'nombre' => $nombre,
            'apellidos' => $apellidos,
            'correo' => Str::slug($nombre . '.' . $apellidos) . '@' . fake()->domainName(),
            'telefono' => '+56 9 ' . fake()->numerify('#### ####'),
            'rol' => RolUsuario::Auxiliar,
            'activo' => true,
            'ultimo_acceso' => null,
        ];
    }

    public function administrador(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Administrador]);
    }

    public function notario(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Notario]);
    }

    public function protocolo(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Protocolo]);
    }

    public function auxiliar(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Auxiliar]);
    }

    public function de(Notaria $notaria): static
    {
        return $this->state(fn (): array => ['tenant_id' => $notaria->id]);
    }
}