<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventoSeguridadRequest extends FormRequest
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
            'tipo_evento' => ['required', 'string'],
            'descripcion' => ['required', 'string'],
            'gravedad' => ['required', 'in:informativo,advertencia,critico'],
        ];
    }
}
