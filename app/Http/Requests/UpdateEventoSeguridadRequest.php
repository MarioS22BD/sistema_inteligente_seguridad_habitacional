<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventoSeguridadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'tipo_evento' => ['sometimes', 'string'],
            'descripcion' => ['sometimes', 'string'],
            'gravedad' => ['sometimes', 'in:informativo,advertencia,critico'],
        ];
    }
}
