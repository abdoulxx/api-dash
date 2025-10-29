<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $userId,
            'password' => 'sometimes|string|min:6|confirmed',
            'is_admin' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'roles' => 'sometimes|array',
            'roles.*' => 'exists:roles,name',
        ];
    }
}
