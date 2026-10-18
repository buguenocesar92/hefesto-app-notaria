<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoDocumento;
use App\Models\Documento;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Documento>
 */
class DocumentoFactory extends Factory
{
    protected $model = Documento::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => \App\Models\Notaria::factory(),
            'expediente_id' => \App\Models\Expediente::factory(),
            'nombre' => fake()->words(2, true),
            'tipo' => null,
            'estado' => EstadoDocumento::Vigente,
            'version_actual' => 1,
            'fecha_documento' => now()->subDays(3)->toDateString(),
            'observacion' => null,
            'creado_por' => null,
            'reemplazado_por' => null,
            'reemplazado_en' => null,
            'reemplazado_por_documento_id' => null,
        ];
    }

    public function reemplazado(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoDocumento::Reemplazado,
            'reemplazado_en' => now(),
        ]);
    }
}