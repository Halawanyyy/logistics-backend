<?php

use App\Modules\Driver\Controllers\DriverController;
use App\Modules\Driver\Controllers\DriverManifestController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('drivers')->group(function () {
    Route::get('/', [DriverController::class, 'index'])->middleware('permission:drivers.view');
    Route::post('/', [DriverController::class, 'store'])->middleware('permission:drivers.create');
    Route::get('/{driver}', [DriverController::class, 'show'])->middleware('permission:drivers.view');
    Route::get('/{driver}/manifest', [DriverController::class, 'manifest'])->middleware('permission:drivers.manifest');
    Route::put('/{driver}', [DriverController::class, 'update'])->middleware('permission:drivers.update');
    Route::delete('/{driver}', [DriverController::class, 'destroy'])->middleware('permission:drivers.delete');
    Route::get('/{driver}/shipments', [DriverController::class, 'shipments'])->middleware('permission:drivers.view');
});

Route::middleware('auth:sanctum')->prefix('driver-manifests')->group(function () {
    Route::get('/', [DriverManifestController::class, 'index'])->middleware('permission:driver_manifests.view');
    Route::post('/generate', [DriverManifestController::class, 'generate'])->middleware('permission:driver_manifests.create');
    Route::get('/{manifest}', [DriverManifestController::class, 'show'])->middleware('permission:driver_manifests.view');
    Route::post('/{manifest}/close', [DriverManifestController::class, 'close'])->middleware('permission:driver_manifests.close');
    Route::get('/{manifest}/print', [DriverManifestController::class, 'print'])->middleware('permission:driver_manifests.print');
});
