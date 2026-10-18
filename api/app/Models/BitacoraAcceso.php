<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitacora de acceso: quien entro, a que expediente y desde donde.
 *
 * Se escribe en modo solo agregar. No hay endpoint para editarla ni borrarla.
 *
 * En una notaria, esto no es una funcion de seguridad: es una excepcion
 * consciente. Datos de patrimonio, familia y sucesiones son datos personales
 * sensibles por la naturaleza misma de la operacion, y la question "quien
 * abrio este expediente y cuando" se le hace a una notaria cuando alguien
 * reclama. Ver docs/07, seccion "Trazabilidad".
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class BitacoraAcceso extends Model
{
    use HasUuids;

    protected $table = 'bitacora_accesos';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'usuario_id',
        'expediente_id',
        'accion',
        'entidad',
        'entidad_id',
        'metodo_http',
        'ruta',
        'direccion_ip',
        'agente_usuario',
        'exito',
        'registrado_en',
    ];

    protected function casts(): array
    {
        return [
            'exito' => 'boolean',
            'registrado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}