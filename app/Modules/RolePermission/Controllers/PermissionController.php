<?php

namespace App\Modules\RolePermission\Controllers;

use App\Modules\RolePermission\Models\Permission;
use App\Modules\RolePermission\Resources\PermissionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionController
{
    public function index(): AnonymousResourceCollection
    {
        $permissions = Permission::query()
            ->orderBy('module')
            ->orderBy('slug')
            ->get();

        return PermissionResource::collection($permissions);
    }

    public function grouped(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('module')
            ->orderBy('slug')
            ->get()
            ->groupBy('module')
            ->map(fn ($group, $module) => [
                'module' => $module,
                'permissions' => PermissionResource::collection($group)->resolve(),
            ])
            ->values();

        return response()->json([
            'data' => $permissions,
        ]);
    }
}
