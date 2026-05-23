<?php

namespace App\Modules\UserManagement\Resources;

use App\Modules\RolePermission\Resources\PermissionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'role_id' => $this->role_id,
            'role_name' => $this->whenLoaded('roleRelation', fn () => $this->roleRelation?->name),
            'role_slug' => $this->whenLoaded('roleRelation', fn () => $this->roleRelation?->slug),
            'permissions' => $this->when(
                $this->relationLoaded('roleRelation') && $this->roleRelation?->relationLoaded('permissions'),
                fn () => PermissionResource::collection($this->roleRelation->permissions)->resolve(),
            ),
            'created_at' => $this->created_at,
        ];
    }
}
