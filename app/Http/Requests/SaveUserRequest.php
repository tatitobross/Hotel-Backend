<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('usuario');

        return [
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user)],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user)],
            'gender' => ['required', 'string', 'max:20'],
            'birth' => ['required', 'date'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'password' => [$user instanceof User ? 'nullable' : 'required', 'string', 'min:8', 'max:200'],
        ];
    }
}
