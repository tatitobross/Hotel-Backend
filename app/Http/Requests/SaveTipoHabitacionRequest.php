<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTipoHabitacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tipo = $this->route('tipo');

        return [
            'nombre' => ['required', 'string', 'max:80', Rule::unique('tipos_habitacion', 'nombre')->ignore($tipo)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'capacidad' => ['required', 'integer', 'between:1,20'],
            'precio_noche' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
