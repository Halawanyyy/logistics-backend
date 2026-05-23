<?php

namespace App\Modules\RolePermission\Controllers;

use App\Models\User;
use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\RolePermission\Models\Role;
use App\Modules\RolePermission\Requests\AssignRoleToUserRequest;
use App\Modules\RolePermission\Services\UserRoleAssignmentService;
use App\Modules\UserManagement\Resources\UserResource;

class UserRoleController
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly UserRoleAssignmentService $userRoleAssignmentService,
    ) {
    }

    public function assignRole(AssignRoleToUserRequest $request, User $user): UserResource
    {
        $role = Role::query()->findOrFail($request->integer('role_id'));
        $oldRoleId = $user->role_id;
        $user = $this->userRoleAssignmentService->assign($user, $role);

        $this->auditLogService->log(
            AuditEventType::ROLE_ASSIGNED,
            'assigned_role',
            $user->fresh(),
            null,
            [
                'user' => $request->user(),
                'old_values' => [
                    'role_id' => $oldRoleId,
                ],
                'new_values' => [
                    'role_id' => $role->id,
                ],
                'metadata' => [
                    'role_name' => $role->name,
                    'role_slug' => $role->slug,
                ],
                'notes' => 'User role assigned.',
            ],
        );

        return new UserResource($user->fresh()->load('roleRelation.permissions'));
    }
}
