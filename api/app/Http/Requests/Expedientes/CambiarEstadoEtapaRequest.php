<?php

declare(strict_types=1);

namespace App\Http\Requests\Expedientes;

use App\Enums\EstadoEtapa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validacion para cambiar el estado de una etapa.
 *
 * Solo valida que el estado sea uno de los conocidos. NO valida que el cambio
 * tenga sentido para el tramite: si una operacion exige un orden, ese orden lo
 * define la notaria en su plantilla, y esta aplicacion no lo conoce.
 */
class CambiarEstadoEtapaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', Rule::in(EstadoEtapa::opciones())],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ];
    }
}