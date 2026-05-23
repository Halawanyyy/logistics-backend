<?php

namespace App\Modules\RolePermission\Controllers;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\RolePermission\Models\Permission;
use App\Modules\RolePermission\Models\Role;
use App\Modules\RolePermission\Requests\StoreRoleRequest;
use App\Modules\RolePermission\Requests\SyncRolePermissionsRequest;
use App\Modules\RolePermission\Requests\UpdateRoleRequest;
use App\Modules\RolePermission\Resources\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(): AnonymousResourceCollection
    {
        $roles = Role::query()
            ->with('permissions')
            ->latest()
            ->get();

        return RoleResource::collection($roles);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $role = Role::query()->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'is_system' => false,
        ]);

        $this->syncRolePermissions($role, $validated['permissions'] ?? []);

        return (new RoleResource($role->fresh()->load('permissions')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Role $role): RoleResource
    {
        return new RoleResource($role->load('permissions'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $validated = $request->validated();

        if ($role->is_system) {
            unset($validated['slug']);
        }

        $role->update($validated);

        if (array_key_exists('permissions', $validated)) {
            $this->syncRolePermissions($role, $validated['permissions'] ?? []);
        }

        return new RoleResource($role->fresh()->load('permissions'));
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json([
                'message' => 'System roles cannot be deleted.',
            ], 422);
        }

        if ($role->users()->exists()) {
            return response()->json([
                'message' => 'Role cannot be deleted while assigned to users.',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): RoleResource
    {
        $permissionIds = $request->validated('permissions');
        $oldPermissionIds = $role->permissions()->pluck('permissions.id')->all();

        $this->syncRolePermissions($role, $permissionIds);

        $this->auditLogService->log(
            AuditEventType::PERMISSION_UPDATED,
            'permissions_synced',
            $role->fresh(),
            null,
            [
                'user' => $request->user(),
                'old_values' => [
                    'permission_ids' => $oldPermissionIds,
                ],
                'new_values' => [
                    'permission_ids' => $permissionIds,
                ],
                'notes' => 'Role permissions synchronized.',
            ],
        );

        return new RoleResource($role->fresh()->load('permissions'));
    }

    /**
     * @param  list<int>  $permissionIds
     */
    private function syncRolePermissions(Role $role, array $permissionIds): void
    {
        if ($role->slug === 'super_admin') {
            $permissionIds = Permission::query()->pluck('id')->all();
        }

        $role->permissions()->sync($permissionIds);
    }
}
