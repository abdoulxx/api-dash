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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_admin' => $this->is_admin,
            'is_active' => $this->is_active,
            'phone' => $this->phone,
            'address' => $this->address,
            'email_verified_at' => $this->email_verified_at?->format('Y-m-d H:i:s'),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'permissions' => $this->when($this->relationLoaded('permissions'), function () {
                return $this->getAllPermissions()->pluck('name');
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
