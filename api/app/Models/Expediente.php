<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NivelSemaforo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un expediente: una operacion en curso. EL CENTRO DEL SISTEMA.
 *
 * De el cuelgan las etapas (donde vive el semaforo), los antecedentes que hay
 * que conseguir, las partes, los documentos y lo facturado.
 *
 * `estado` se DERIVA de las etapas, no se elige a mano. Ver
 * `App\Services\Expedientes\EstadoDeExpediente`.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Expediente extends Model
{
    use HasUuids;

    protected $table = 'expedientes';

    protected $fillable = [
        'tenant_id',
        'folio',
        'numero_protocolo',
        'tipo_operacion_id',
        'profesional_responsable_id',
        'usuario_responsable_id',
        'estado',
        'descripcion',
        'detalle',
        'fecha_inicio',
        'fecha_cierre',
        'dias_alerta_por_defecto',
        'dias_critico_por_defecto',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoExpediente::class,
            'fecha_inicio' => 'datetime',
            'fecha_cierre' => 'datetime',
            'dias_alerta_por_defecto' => 'integer',
            'dias_critico_por_defecto' => 'integer',
        ];
    }

    public function tipoOperacion(): BelongsTo
    {
        return $this->belongsTo(TipoOperacion::class, 'tipo_operacion_id');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_responsable_id');
    }

    public function etapas(): HasMany
    {
        return $this->hasMany(Etapa::class)->orderBy('orden');
    }

    public function antecedentes(): HasMany
    {
        return $this->hasMany(Antecedente::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    public function cobranzas(): HasMany
    {
        return $this->hasMany(Cobranza::class);
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(AlertaSemaforo::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(AgendaCita::class);
    }

    /**
     * Las partes del expediente, con el rol que tienen EN ESTA operacion.
     *
     * Es la razon de ser de `expediente_partes`: el rol viaja con la
     * relacion, no con la persona. La misma persona puede ser compradora en
     * un expediente y testigo en otro.
     */
    public function partes(): HasMany
    {
        return $this->belongsToMany(Parte::class, 'expediente_partes', 'expediente_id', 'parte_id')
            ->withPivot(['id', 'rol', 'es_principal', 'observacion'])
            ->withTimestamps();
    }

    /** Expedientes que siguen abiertos. */
    public function scopeAbiertos(Builder $consulta): Builder
    {
        return $consulta->where('estado', EstadoExpediente::EnTramite->value);
    }

    /**
     * El peor nivel del semaforo entre las etapas del expediente.
     *
     * Es lo que permite ordenar el listado de expedientes por urgencia sin
     * tener que abrir cada uno. Si ninguna etapa tiene umbral configurado,
     * devuelve `SinUmbral`, que ordena al final.
     */
    public function peorNivel(): NivelSemaforo
    {
        return $this->etapas
            ->map(static fn (Etapa $etapa): NivelSemaforo => $etapa->semaforo()->nivel)
            ->sortByDesc(static fn (NivelSemaforo $nivel): int => $nivel->urgencia())
            ->first() ?? NivelSemaforo::SinUmbral;
    }
}