<?php

use App\Modules\RolePermission\Controllers\PermissionController;
use App\Modules\RolePermission\Controllers\RoleController;
use App\Modules\RolePermission\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->middleware('permission:roles.view');
        Route::post('/', [RoleController::class, 'store'])->middleware('permission:roles.create');
        Route::get('/{role}', [RoleController::class, 'show'])->middleware('permission:roles.view');
        Route::put('/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');
        Route::post('/{role}/permissions', [RoleController::class, 'syncPermissions'])->middleware('permission:permissions.assign');
    });

    Route::prefix('permissions')->group(function () {
        Route::get('/', [PermissionController::class, 'index'])->middleware('permission:permissions.view');
        Route::get('/grouped', [PermissionController::class, 'grouped'])->middleware('permission:permissions.view');
    });

    Route::post('/users/{user}/assign-role', [UserRoleController::class, 'assignRole'])
        ->middleware(['permission:users.update', 'permission:roles.update']);
});
