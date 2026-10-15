<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveHuespedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $huesped = $this->route('huesped');

        return [
            'nombre' => ['required', 'string', 'max:80'],
            'apellido' => ['required', 'string', 'max:80'],
            'documento' => ['required', 'string', 'max:30', Rule::unique('huespedes', 'documento')->ignore($huesped)],
            'telefono' => ['required', 'string', 'max:30'],
            'correo' => ['nullable', 'email', 'max:150', Rule::unique('huespedes', 'correo')->ignore($huesped)],
            'nacionalidad' => ['nullable', 'string', 'max:60'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
