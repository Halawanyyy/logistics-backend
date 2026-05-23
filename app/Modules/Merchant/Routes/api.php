<?php

use App\Modules\Merchant\Controllers\MerchantController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('merchants')->group(function () {
    Route::get('/', [MerchantController::class, 'index'])->middleware('permission:merchants.view');
    Route::post('/', [MerchantController::class, 'store'])->middleware('permission:merchants.create');
    Route::get('/{merchant}', [MerchantController::class, 'show'])->middleware('permission:merchants.view');
    Route::get('/{merchant}/dashboard/summary', [MerchantController::class, 'dashboardSummary'])->middleware('permission:dashboard.merchant_view');
    Route::put('/{merchant}', [MerchantController::class, 'update'])->middleware('permission:merchants.update');
    Route::delete('/{merchant}', [MerchantController::class, 'destroy'])->middleware('permission:merchants.delete');
});
