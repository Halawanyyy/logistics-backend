<?php

use App\Modules\Dashboard\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('dashboard')->group(function () {
    Route::get('/summary', [DashboardController::class, 'summary'])->middleware('permission:dashboard.view');
});
