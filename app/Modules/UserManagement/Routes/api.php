<?php

use App\Modules\UserManagement\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view');
    Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create');
    Route::get('/{user}', [UserController::class, 'show'])->middleware('permission:users.view');
    Route::put('/{user}', [UserController::class, 'update'])->middleware('permission:users.update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete');
});
