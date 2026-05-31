<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Auth\Controllers\AuthController;

Route::prefix('auth')->group(function () {
    Route::get('/ping', function () {
        return response()->json([
            'message' => __('auth.module_working'),
        ]);
    });

    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});
