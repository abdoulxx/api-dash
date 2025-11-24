<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get primary role name for quick UI display (first role)
        $primaryRole = $this->whenLoaded('roles', function () {
            return $this->roles->first()?->name;
        });

        // Simplified manager resource (only essential fields)
        $manager = $this->whenLoaded('manager', function () {
            return [
                'id' => $this->manager->id,
                'firstname' => $this->manager->firstname,
                'lastname' => $this->manager->lastname,
                'email' => $this->manager->email,
                'is_admin' => $this->manager->is_admin,
                'is_active' => $this->manager->is_active,
            ];
        });

        $photoUrl = null;
        if ($this->photo) {
            $photoUrl = asset('storage/' . $this->photo);
        }

        $data = [
            'id' => $this->id,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'email' => $this->email,
            'photo' => $photoUrl,
            'is_admin' => $this->is_admin,
            'is_active' => $this->is_active,
            'statut' => $this->statut, // For form dropdown (UI display)
            'fonction' => $this->fonction,
            'departement' => $this->departement,
            'manager' => $manager,
            'manager_id' => $this->manager_id,
            'phone' => $this->phone,
            'address' => $this->address,
            'last_login_at' => $this->last_login_at?->format('Y-m-d H:i:s'),
            'email_verified_at' => $this->email_verified_at?->format('Y-m-d H:i:s'),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'role' => $primaryRole,
            'permissions' => $this->when($this->relationLoaded('permissions') || $this->relationLoaded('roles'), function () {
                return $this->getAllPermissions()->pluck('name');
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];

        // Filtrer les valeurs null pour les champs optionnels (garder les champs essentiels même s'ils sont null)
        // On garde les champs essentiels comme id, email, photo, is_admin, is_active, statut, roles, permissions
        // On filtre seulement les champs optionnels qui sont null
        $optionalFields = ['firstname', 'lastname', 'fonction', 'departement', 'manager', 'manager_id', 'phone', 'address', 'last_login_at', 'email_verified_at'];
        
        foreach ($optionalFields as $field) {
            if (isset($data[$field]) && $data[$field] === null) {
                unset($data[$field]);
            }
        }
        
        // La photo est toujours incluse (même si null) pour que le frontend sache qu'il peut uploader

        return $data;
    }
}
