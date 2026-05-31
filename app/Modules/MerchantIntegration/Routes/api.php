<?php

use App\Modules\MerchantIntegration\Controllers\IntegrationConnectionController;
use App\Modules\MerchantIntegration\Controllers\IntegrationOrderController;
use App\Modules\MerchantIntegration\Controllers\MerchantIntegrationTokenController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('merchants/{merchant}/integration-tokens')->group(function () {
        Route::get('/', [MerchantIntegrationTokenController::class, 'index'])->middleware('permission:merchant_integrations.view');
        Route::post('/', [MerchantIntegrationTokenController::class, 'store'])->middleware('permission:merchant_integrations.create');
    });

    Route::prefix('merchant-integration-tokens')->group(function () {
        Route::get('/{token}', [MerchantIntegrationTokenController::class, 'show'])->middleware('permission:merchant_integrations.view');
        Route::post('/{token}/revoke', [MerchantIntegrationTokenController::class, 'revoke'])->middleware('permission:merchant_integrations.revoke');
        Route::delete('/{token}', [MerchantIntegrationTokenController::class, 'destroy'])->middleware('permission:merchant_integrations.delete');
    });
});

Route::middleware(['merchant.integration.token', 'throttle:integration-api'])->prefix('integrations')->group(function () {
    Route::get('/me', [IntegrationConnectionController::class, 'me']);
    Route::post('/orders', [IntegrationOrderController::class, 'store']);
});
