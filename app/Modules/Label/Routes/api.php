<?php

use App\Modules\Label\Controllers\LabelController;
use App\Modules\Label\Controllers\ResourceLabelController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('labels')->group(function () {
        Route::get('/templates', [LabelController::class, 'templates'])->middleware('permission:labels.view');
        Route::get('/settings', [LabelController::class, 'settings'])->middleware('permission:labels.view');
        Route::post('/preview', [LabelController::class, 'preview'])->middleware('permission:labels.preview');
        Route::post('/test', [LabelController::class, 'test'])->middleware('permission:labels.preview');
    });

    Route::get('/warehouse-products/{warehouseProduct}/label', [ResourceLabelController::class, 'warehouseProductLabel'])
        ->middleware('permission:warehouse_products.view');
    Route::post('/warehouse-products/labels/bulk', [ResourceLabelController::class, 'bulkWarehouseProductLabels'])
        ->middleware('permission:warehouse_products.view');

    Route::get('/shipments/{shipment}/label', [ResourceLabelController::class, 'shipmentLabel'])
        ->middleware('permission:shipments.print');
    Route::get('/shipments/{shipment}/waybill', [ResourceLabelController::class, 'shipmentWaybill'])
        ->middleware('permission:shipments.print');
    Route::post('/shipments/labels/bulk', [ResourceLabelController::class, 'bulkShipmentLabels'])
        ->middleware('permission:shipments.print');

    Route::get('/orders/{order}/label', [ResourceLabelController::class, 'orderLabel'])
        ->middleware('permission:orders.view');

    Route::get('/warehouse-bins/{warehouseBin}/label', [ResourceLabelController::class, 'warehouseBinLabel'])
        ->middleware('permission:warehouse_locations.view');
});
