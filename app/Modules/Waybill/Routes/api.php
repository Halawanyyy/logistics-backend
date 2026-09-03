<?php

use App\Modules\Waybill\Controllers\WaybillBatchController;
use App\Modules\Waybill\Controllers\WaybillController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/waybills/validate', [WaybillController::class, 'validateWaybill']);

    Route::prefix('waybill-batches')->group(function (): void {
        Route::get('/', [WaybillBatchController::class, 'index'])->middleware('permission:waybills.view');
        Route::post('/', [WaybillBatchController::class, 'store'])->middleware('permission:waybills.generate');
        Route::get('/{batch}', [WaybillBatchController::class, 'show'])->middleware('permission:waybills.view');
        Route::get('/{batch}/print', [WaybillBatchController::class, 'print'])->middleware('permission:waybills.print');
        Route::get('/{batch}/simple-labels/print', [WaybillBatchController::class, 'simplePrint'])->middleware('permission:waybills.print');
    });

    Route::get('/waybills/{waybill}/label', [WaybillController::class, 'label'])->middleware('permission:waybills.view');
    Route::get('/waybills/{waybill}/simple-label', [WaybillController::class, 'simpleLabel'])->middleware('permission:waybills.print');
    Route::post('/waybills/{waybill}/void', [WaybillController::class, 'void'])->middleware('permission:waybills.void');
});
