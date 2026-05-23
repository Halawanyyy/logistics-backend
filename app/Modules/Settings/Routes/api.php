<?php

use App\Modules\Settings\Controllers\SystemSettingController;
use Illuminate\Support\Facades\Route;

Route::get('/public/settings', [SystemSettingController::class, 'publicIndex']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('settings')->group(function () {
        Route::get('/', [SystemSettingController::class, 'index'])->middleware('permission:settings.view');
        Route::get('/groups', [SystemSettingController::class, 'groups'])->middleware('permission:settings.groups');
        Route::get('/group/{group}', [SystemSettingController::class, 'group'])->middleware('permission:settings.view');
        Route::put('/bulk-update', [SystemSettingController::class, 'bulkUpdate'])->middleware('permission:settings.bulk_update');
        Route::get('/{setting}', [SystemSettingController::class, 'show'])->middleware('permission:settings.view');
        Route::put('/{setting}', [SystemSettingController::class, 'update'])->middleware('permission:settings.update');
    });
});
