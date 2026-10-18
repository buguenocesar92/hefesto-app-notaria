<?php

declare(strict_types=1);

namespace App\Http\Requests\Expedientes;

use App\Enums\EstadoEtapa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validacion para abrir un expediente.
 *
 * `numero_protocolo` es OPCIONAL a proposito. Es normal que al abrir el
 * expediente todavia no exista, y el sistema no lo genera: lo pone la
 * notaria, con el criterio que le corresponda.
 *
 * Si el campo viene presente y tiene una forma que no corresponde a un
 * identificador de protocolo chileno, se rechaza. Es una validacion de
 * FORMA, no de fondo: no comprueba si el numero existe ni si corresponde a
 * esta operacion. Eso no le corresponde a esta aplicacion.
 */
class AbrirExpedienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tipo_operacion_id' => ['required', 'uuid'],

            'numero_protocolo' => [
                'nullable',
                'string',
                'max:60',
                // Forma, no fondo. Ver la nota del docblock.
                'regex:/^[0-9A-Za-z\-\.\/ ]+$/',
            ],

            'descripcion' => ['nullable', 'string', 'max:250'],
            'profesional_id' => ['nullable', 'uuid'],
            'fecha_inicio' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'numero_protocolo.regex' => 'El numero de protocolo solo admite letras, numeros, punto, guion y barra. '
                . 'Si el formato es otro, el sistema no lo restringe por que no conoce el '
                . 'formato que usa esta notaria.',
        ];
    }
}