<?php

namespace App\Modules\RolePermission\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'is_system' => (bool) $this->is_system,
            'permissions' => $this->whenLoaded('permissions', function (): array {
                return $this->permissions
                    ->sortBy([
                        ['module', 'asc'],
                        ['slug', 'asc'],
                    ])
                    ->groupBy('module')
                    ->map(fn ($permissions) => PermissionResource::collection($permissions)->resolve())
                    ->toArray();
            }),
            'created_at' => $this->created_at,
        ];
    }
}
