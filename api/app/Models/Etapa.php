<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoEtapa;
use App\Enums\NivelSemaforo;
use App\Services\Semaforo\SemaforoEtapa;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una etapa del tramite de un expediente. AQUI VIVE EL SEMAFORO.
 *
 * `dias_alerta` y `dias_critico` son N y M: los umbrales INTERNOS que la
 * notaria configuro. No son plazos legales. Si estan en NULL, el semaforo
 * no marca nada.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Etapa extends Model
{
    use HasUuids;

    protected $table = 'etapas';

    protected $fillable = [
        'tenant_id',
        'expediente_id',
        'etapa_plantilla_id',
        'orden',
        'nombre',
        'descripcion',
        'estado',
        'requiere_antecedente',
        'dias_alerta',
        'dias_critico',
        'iniciada_en',
        'terminada_en',
        'profesional_responsable_id',
        'usuario_responsable_id',
        'dias_en_estado',
        'nivel_semaforo',
        'semaforo_calculado_en',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoEtapa::class,
            'nivel_semaforo' => NivelSemaforo::class,
            'requiere_antecedente' => 'boolean',
            'orden' => 'integer',
            'dias_alerta' => 'integer',
            'dias_critico' => 'integer',
            'dias_en_estado' => 'integer',
            'iniciada_en' => 'datetime',
            'terminada_en' => 'datetime',
            'semaforo_calculado_en' => 'datetime',
        ];
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_responsable_id');
    }

    public function antecedentes(): HasMany
    {
        return $this->hasMany(Antecedente::class);
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(AlertaSemaforo::class);
    }

    /**
     * Desde cuando se cuentan los dias de espera.
     *
     * Si la etapa empezo, desde su inicio. Si sigue `pendiente`, la espera
     * se cuenta desde el inicio del expediente: una etapa que nunca empezo
     * igual lleva esperando desde que se abrio el tramite.
     *
     * Si no hay ninguna de las dos fechas, se devuelve null, y el semaforo
     * cuenta cero dias en vez de inventar una.
     */
    public function referenciaParaContar(): ?CarbonImmutable
    {
        if ($this->iniciada_en !== null) {
            return CarbonImmutable::instance($this->iniciada_en);
        }

        $inicioExpediente = $this->expediente?->fecha_inicio;

        if ($inicioExpediente === null) {
            return null;
        }

        return CarbonImmutable::instance($inicioExpediente)->startOfDay();
    }

    /**
     * El semaforo de esta etapa, calculado en el momento.
     */
    public function semaforo(): SemaforoEtapa
    {
        return SemaforoEtapa::calcular(
            estado: $this->estado,
            diasAlerta: $this->dias_alerta,
            diasCritico: $this->dias_critico,
            referencia: $this->referenciaParaContar(),
        );
    }

    /**
     * Etapas que pueden estar esperando (las que el semaforo puede marcar).
     */
    public function scopeEsperando(Builder $consulta): Builder
    {
        return $consulta->whereIn('estado', [
            EstadoEtapa::Pendiente->value,
            EstadoEtapa::EnCurso->value,
            EstadoEtapa::Bloqueada->value,
        ]);
    }

    /**
     * Lo que el tablero de atrasos consulta: etapas con dias acumulados por
     * encima de un minimo.
     *
     * El filtro va en SQL y no en PHP porque el tablero se dibuja para toda
     * la notaria de una vez. `COALESCE(iniciada_en, creada_en)` replica la
     * misma regla de `referenciaParaContar()`: si la etapa nunca empezo, se
     * cuenta desde que se creo el registro.
     */
    public function scopeConDiasMinimos(Builder $consulta, int $dias): Builder
    {
        return $consulta->esperando()
            ->whereRaw(
                'COALESCE(iniciada_en, creada_en) <= (CURRENT_TIMESTAMP - make_interval(days => ?))',
                [$dias]
            );
    }
}