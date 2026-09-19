<?php

use App\Modules\Branding\Controllers\BrandingController;
use Illuminate\Support\Facades\Route;

// Public unauthenticated branding endpoint for login page and pre-auth layouts
Route::get('/public/branding', [BrandingController::class, 'publicBranding']);

// Protected admin branding routes
Route::middleware('auth:sanctum')->group(function () {
    // Both /admin/settings/branding/logo and /settings/branding/logo are supported
    Route::group(['middleware' => 'permission:settings.update'], function () {
        Route::post('/settings/branding/logo', [BrandingController::class, 'uploadLogo']);
        Route::delete('/settings/branding/logo', [BrandingController::class, 'resetLogo']);

        Route::post('/admin/settings/branding/logo', [BrandingController::class, 'uploadLogo']);
        Route::delete('/admin/settings/branding/logo', [BrandingController::class, 'resetLogo']);

        // Super Admin managing specific company
        Route::post('/admin/companies/{company}/branding/logo', [BrandingController::class, 'uploadLogo']);
    });
});
