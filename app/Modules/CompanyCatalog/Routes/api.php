<?php

use App\Modules\CompanyCatalog\Controllers\CompanyCatalogItemController;
use App\Modules\CompanyCatalog\Controllers\MerchantShopController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('company-catalog-items')->group(function () {
        Route::get('/', [CompanyCatalogItemController::class, 'index'])->middleware('permission:company_catalog.view');
        Route::post('/', [CompanyCatalogItemController::class, 'store'])->middleware('permission:company_catalog.create');
        Route::get('/{companyCatalogItem}', [CompanyCatalogItemController::class, 'show'])->middleware('permission:company_catalog.view');
        Route::put('/{companyCatalogItem}', [CompanyCatalogItemController::class, 'update'])->middleware('permission:company_catalog.update');
        Route::delete('/{companyCatalogItem}', [CompanyCatalogItemController::class, 'destroy'])->middleware('permission:company_catalog.delete');
    });

    Route::prefix('merchant-shop')->middleware('permission:merchant_shop.view')->group(function () {
        Route::get('/catalog', [MerchantShopController::class, 'catalog']);
        Route::get('/pricing', [MerchantShopController::class, 'pricing']);
    });

    Route::get('/merchants/{merchant}/shop-preview', [MerchantShopController::class, 'preview'])
        ->middleware('permission:merchant_shop.view');
});
