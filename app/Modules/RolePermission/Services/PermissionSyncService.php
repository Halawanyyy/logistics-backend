<?php

namespace App\Modules\RolePermission\Services;

use App\Modules\RolePermission\Models\Permission;
use Illuminate\Support\Collection;

class PermissionSyncService
{
    /**
     * @param  list<array{module: string, name: string, slug: string, description?: string|null}>  $definitions
     * @return Collection<int, Permission>
     */
    public function sync(array $definitions): Collection
    {
        $permissions = collect();

        foreach ($definitions as $definition) {
            $permissions->push(Permission::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'module' => $definition['module'],
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? null,
                ],
            ));
        }

        return $permissions;
    }
}
