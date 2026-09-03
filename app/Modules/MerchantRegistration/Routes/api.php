<?php

use App\Modules\MerchantRegistration\Controllers\MerchantRegistrationAdminController;
use App\Modules\MerchantRegistration\Controllers\MerchantRegistrationController;
use Illuminate\Support\Facades\Route;

Route::post('/merchant-portal/register', [MerchantRegistrationController::class, 'store'])
    ->middleware('throttle:merchant-registration');

Route::middleware(['auth:sanctum'])->prefix('merchant-registration-requests')->group(function (): void {
    Route::get('/', [MerchantRegistrationAdminController::class, 'index'])
        ->middleware('permission:merchant_registration.view');
    Route::get('/{merchant}', [MerchantRegistrationAdminController::class, 'show'])
        ->middleware('permission:merchant_registration.view');
    Route::post('/{merchant}/approve', [MerchantRegistrationAdminController::class, 'approve'])
        ->middleware('permission:merchant_registration.approve');
    Route::post('/{merchant}/reject', [MerchantRegistrationAdminController::class, 'reject'])
        ->middleware('permission:merchant_registration.reject');
});
