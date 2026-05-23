<?php

namespace App\Modules\RolePermission\Services;

use App\Models\User;
use App\Modules\RolePermission\Models\Role;
use App\Modules\UserManagement\Enums\UserRole;

class UserRoleAssignmentService
{
    public function assign(User $user, Role $role): User
    {
        $attributes = [
            'role_id' => $role->id,
        ];

        if (in_array($role->slug, UserRole::values(), true)) {
            $attributes['role'] = $role->slug;
        } elseif (! filled($user->role)) {
            $attributes['role'] = UserRole::STAFF;
        }

        $user->update($attributes);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function applyRoleAttributes(array $attributes): array
    {
        $role = null;

        if (isset($attributes['role_id'])) {
            $role = Role::query()->find($attributes['role_id']);
        } elseif (isset($attributes['role'])) {
            $role = Role::query()->where('slug', $attributes['role'])->first();
        }

        if ($role !== null) {
            $attributes['role_id'] = $role->id;
        }

        return $attributes;
    }
}
