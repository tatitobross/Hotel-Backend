<?php

namespace App\Http\Requests;

use App\Models\Habitacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveHabitacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $habitacion = $this->route('habitacion');

        return [
            'numero' => ['required', 'string', 'max:20', Rule::unique('habitaciones', 'numero')->ignore($habitacion)],
            'piso' => ['required', 'integer', 'between:0,100'],
            'tipo_habitacion_id' => ['required', 'integer', 'exists:tipos_habitacion,id'],
            'estado' => ['sometimes', Rule::in(Habitacion::ESTADOS)],
            'activo' => ['sometimes', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ];
    }
}
