<?php

use App\Modules\Shipment\Controllers\TrackingController;
use App\Modules\Shipment\Controllers\DeliveryAttemptController;
use App\Modules\Shipment\Controllers\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::get('/track/{shipment_number}', [TrackingController::class, 'show'])->middleware('throttle:public-tracking');

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('shipments')->group(function () {
        Route::get('/', [ShipmentController::class, 'index'])->middleware('permission:shipments.view');
        Route::get('/print-list', [ShipmentController::class, 'printList'])->middleware('permission:shipments.print');
        Route::get('/{shipment}', [ShipmentController::class, 'show'])->middleware('permission:shipments.view');
        Route::patch('/{shipment}/status', [ShipmentController::class, 'updateStatus'])->middleware('permission:shipments.update_status');
        Route::post('/{shipment}/complete-delivery', [ShipmentController::class, 'completeDelivery'])->middleware('permission:shipments.update_status');
        Route::post('/{shipment}/assign-driver', [ShipmentController::class, 'assignDriver'])->middleware('permission:shipments.assign_driver');
        Route::post('/{shipment}/delivery-attempt', [DeliveryAttemptController::class, 'store'])->middleware('permission:delivery_attempts.create');
    });

    Route::get('/delivery-attempts', [DeliveryAttemptController::class, 'index'])->middleware('permission:delivery_attempts.view');
    Route::post('/orders/{order}/shipments', [ShipmentController::class, 'createFromOrder'])->middleware('permission:orders.create_shipment');
});
