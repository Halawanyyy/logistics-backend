<?php

use App\Modules\Barcode\Controllers\BarcodeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('barcodes')->group(function () {
    Route::post('/lookup', [BarcodeController::class, 'lookup'])->middleware('permission:barcodes.lookup');
    Route::post('/scan/shipment-status', [BarcodeController::class, 'scanShipmentStatus'])->middleware('permission:barcodes.scan');
    Route::post('/scan/product-lookup', [BarcodeController::class, 'scanProductLookup'])->middleware('permission:barcodes.scan');
});
