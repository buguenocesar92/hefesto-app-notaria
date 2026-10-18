<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoEtapa;
use App\Models\Etapa;
use App\Models\Expediente;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Etapa>
 */
class EtapaFactory extends Factory
{
    protected $model = Etapa::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => \App\Models\Notaria::factory(),
            'expediente_id' => Expediente::factory(),
            'etapa_plantilla_id' => null,
            'orden' => 1,
            'nombre' => fake()->words(2, true),
            'descripcion' => null,
            'estado' => EstadoEtapa::Pendiente,
            'requiere_antecedente' => false,
            // Sin umbral: el semaforo no marca nada hasta que la notaria lo
            // defina. Ver SemaforoEtapa.
            'dias_alerta' => null,
            'dias_critico' => null,
            'iniciada_en' => null,
            'terminada_en' => null,
            'dias_en_estado' => 0,
            'nivel_semaforo' => 'sin_umbral',
            'semaforo_calculado_en' => null,
        ];
    }

    public function enCurso(?int $diasAlerta = null, ?int $diasCritico = null): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoEtapa::EnCurso,
            'iniciada_en' => now(),
            'dias_alerta' => $diasAlerta,
            'dias_critico' => $diasCritico,
        ]);
    }

    public function terminada(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoEtapa::Terminada,
            'iniciada_en' => now()->subDays(10),
            'terminada_en' => now()->subDay(),
        ]);
    }

    /** Etapa que lleva N dias esperando. */
    public function esperandoDias(int $dias, ?int $diasAlerta = null, ?int $diasCritico = null): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoEtapa::EnCurso,
            'iniciada_en' => now()->subDays($dias),
            'terminada_en' => null,
            'dias_alerta' => $diasAlerta,
            'dias_critico' => $diasCritico,
        ]);
    }
}