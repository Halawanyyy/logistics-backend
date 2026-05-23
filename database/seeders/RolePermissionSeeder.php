<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\RolePermission\Models\Permission;
use App\Modules\RolePermission\Models\Role;
use App\Modules\RolePermission\Services\PermissionSyncService;
use App\Modules\RolePermission\Support\RolePermissionDefaults;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        /** @var PermissionSyncService $permissionSyncService */
        $permissionSyncService = app(PermissionSyncService::class);
        $permissionSyncService->sync(RolePermissionDefaults::permissions());

        $roles = collect(RolePermissionDefaults::roles())
            ->mapWithKeys(function (array $definition): array {
                $role = Role::query()->withTrashed()->updateOrCreate(
                    ['slug' => $definition['slug']],
                    [
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'is_system' => $definition['is_system'],
                        'status' => $definition['status'],
                        'deleted_at' => null,
                    ],
                );

                return [$role->slug => $role];
            });

        $permissionIdsBySlug = Permission::query()->pluck('id', 'slug');

        foreach (RolePermissionDefaults::rolePermissionSlugs() as $roleSlug => $permissionSlugs) {
            /** @var Role|null $role */
            $role = $roles->get($roleSlug);

            if ($role === null) {
                continue;
            }

            $role->permissions()->sync(
                collect($permissionSlugs)
                    ->map(fn (string $slug) => $permissionIdsBySlug->get($slug))
                    ->filter()
                    ->values()
                    ->all(),
            );
        }

        /** @var Role $superAdminRole */
        $superAdminRole = $roles->get('super_admin');
        $superAdminRole->permissions()->sync(Permission::query()->pluck('id')->all());

        $roleIdsBySlug = $roles->mapWithKeys(fn (Role $role) => [$role->slug => $role->id]);

        User::query()
            ->select(['id', 'role', 'role_id'])
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($roleIdsBySlug): void {
                foreach ($users as $user) {
                    if ($user->role_id !== null) {
                        continue;
                    }

                    $mappedRoleId = $roleIdsBySlug->get($user->role);

                    if ($mappedRoleId !== null) {
                        $user->forceFill(['role_id' => $mappedRoleId])->save();
                    }
                }
            });

        if (! User::query()->where('role_id', $superAdminRole->id)->exists()) {
            $candidate = User::query()
                ->where('role', 'admin')
                ->orderBy('id')
                ->first() ?? User::query()->orderBy('id')->first();

            if ($candidate !== null) {
                $candidate->forceFill(['role_id' => $superAdminRole->id])->save();
            }
        }
    }
}
