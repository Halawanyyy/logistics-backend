<?php

use App\Modules\Order\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index'])->middleware('permission:orders.view');
    Route::post('/', [OrderController::class, 'store'])->middleware('permission:orders.create');
    Route::post('/{order}/confirm', [OrderController::class, 'confirm'])->middleware('permission:orders.confirm');
    Route::get('/{order}', [OrderController::class, 'show'])->middleware('permission:orders.view');
    Route::put('/{order}', [OrderController::class, 'update'])->middleware('permission:orders.update');
    Route::delete('/{order}', [OrderController::class, 'destroy'])->middleware('permission:orders.delete');
});
