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
        // Récupérer l'ID de l'utilisateur depuis la route
        // Peut être 'user' ou 'admin' selon la route
        $userId = $this->route('user') ?? $this->route('admin');
        
        // Si userId est null, essayer de le récupérer depuis l'ID dans l'URL
        if (!$userId) {
            $userId = $this->route('id');
        }

        return [
            'name' => 'sometimes|string|max:255',
            'firstname' => 'sometimes|string|max:255',
            'lastname' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . ($userId ?? 'NULL'),
            'password' => 'sometimes|nullable|string|min:6|confirmed',
            'password_confirmation' => 'required_with:password|string|min:6',
            'is_admin' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'statut' => 'sometimes|string|in:Actif,Inactif,En attente',
            'fonction' => 'nullable|string|max:255',
            'departement' => 'nullable|string|max:255',
            'manager_id' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if ($value && !preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i', $value)) {
                        $fail('Le manager_id doit être un ULID valide (format: 26 caractères alphanumériques).');
                    }
                },
                'exists:users,id',
            ],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'roles' => 'nullable|array',
            'roles.*' => 'required_with:roles|string|exists:roles,name',
            'role' => 'nullable|string|exists:roles,name', // Single role alias
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'manager_id.exists' => 'Le manager sélectionné n\'existe pas dans la base de données.',
            'roles.*.exists' => 'Le rôle :input n\'existe pas. Rôles disponibles: ' . $this->getAvailableRoles(),
            'role.exists' => 'Le rôle sélectionné n\'existe pas. Rôles disponibles: ' . $this->getAvailableRoles(),
            'roles.*.required_with' => 'Chaque rôle doit être spécifié lorsque le champ roles est fourni.',
        ];
    }

    /**
     * Get available roles for error message
     */
    private function getAvailableRoles(): string
    {
        try {
            $roles = \Spatie\Permission\Models\Role::pluck('name')->toArray();
            return implode(', ', $roles);
        } catch (\Exception $e) {
            return 'Aucun rôle disponible';
        }
    }
}
