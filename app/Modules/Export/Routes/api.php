<?php

use App\Modules\Export\Controllers\ExportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('exports')->group(function () {
        Route::get('/', [ExportController::class, 'index'])->middleware('permission:exports.view')->name('exports.index');
        Route::post('/{type}', [ExportController::class, 'store'])->middleware('permission:exports.create')->name('exports.store');
        Route::get('/{exportLog}', [ExportController::class, 'show'])->middleware('permission:exports.view')->name('exports.show');
        Route::get('/{exportLog}/download', [ExportController::class, 'download'])->middleware('permission:exports.download')->name('exports.download');
    });
});
