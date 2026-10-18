<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cobro REGISTRADO. Esta aplicacion NO emite el documento tributario
 * electronico.
 *
 * Hefesto tiene el kit LibreDTE, pero su version publicada es una beta y su
 * API es de solo lectura: no permite automatizar la emision. Por eso aqui no
 * hay codigo que emita, timbre, anule ni envie nada. Lo unico que se guarda
 * es el registro de lo que la notaria ya emitio por su cuenta.
 *
 * Ver docs/05, seccion "Facturacion electronica: lo que no se hace".
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Cobranza extends Model
{
    use HasUuids;

    protected $table = 'cobranzas';

    protected $fillable = [
        'tenant_id',
        'expediente_id',
        'parte_id',
        'concepto',
        'fecha_emision',
        'monto',
        'moneda',
        'estado',
        'referencia_documento_tributario',
        'observacion',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoCobranza::class,
            'fecha_emision' => 'datetime',
            'monto' => 'decimal:2',
        ];
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function parte(): BelongsTo
    {
        return $this->belongsTo(Parte::class);
    }
}