<?php

use App\Modules\LocationPricing\Controllers\AreaController;
use App\Modules\LocationPricing\Controllers\GovernorateController;
use App\Modules\LocationPricing\Controllers\PricingPlanController;
use App\Modules\LocationPricing\Controllers\PricingPlanRateController;
use App\Modules\LocationPricing\Controllers\ShippingRateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('governorates')->group(function () {
        Route::get('/', [GovernorateController::class, 'index'])->middleware('permission:locations.view');
        Route::post('/', [GovernorateController::class, 'store'])->middleware('permission:locations.create');
        Route::get('/{governorate}', [GovernorateController::class, 'show'])->middleware('permission:locations.view');
        Route::put('/{governorate}', [GovernorateController::class, 'update'])->middleware('permission:locations.update');
        Route::delete('/{governorate}', [GovernorateController::class, 'destroy'])->middleware('permission:locations.delete');
    });

    Route::prefix('areas')->group(function () {
        Route::get('/', [AreaController::class, 'index'])->middleware('permission:locations.view');
        Route::post('/', [AreaController::class, 'store'])->middleware('permission:locations.create');
        Route::get('/{area}', [AreaController::class, 'show'])->middleware('permission:locations.view');
        Route::put('/{area}', [AreaController::class, 'update'])->middleware('permission:locations.update');
        Route::delete('/{area}', [AreaController::class, 'destroy'])->middleware('permission:locations.delete');
    });

    Route::prefix('shipping-rates')->group(function () {
        Route::get('/', [ShippingRateController::class, 'index'])->middleware('permission:shipping_rates.view');
        Route::post('/', [ShippingRateController::class, 'store'])->middleware('permission:shipping_rates.create');
        Route::post('/calculate', [ShippingRateController::class, 'calculate'])->middleware('permission:shipping_rates.view');
        Route::get('/{shippingRate}', [ShippingRateController::class, 'show'])->middleware('permission:shipping_rates.view');
        Route::put('/{shippingRate}', [ShippingRateController::class, 'update'])->middleware('permission:shipping_rates.update');
        Route::delete('/{shippingRate}', [ShippingRateController::class, 'destroy'])->middleware('permission:shipping_rates.delete');
        Route::post('/{shippingRate}/restore', [ShippingRateController::class, 'restore'])->middleware('permission:shipping_rates.delete')->withTrashed();
        Route::delete('/{shippingRate}/force', [ShippingRateController::class, 'forceDestroy'])->middleware('permission:shipping_rates.delete')->withTrashed();
    });

    Route::prefix('pricing-plans')->group(function () {
        Route::get('/', [PricingPlanController::class, 'index'])->middleware('permission:pricing_plans.view');
        Route::post('/', [PricingPlanController::class, 'store'])->middleware('permission:pricing_plans.create');
        Route::get('/{pricingPlan}', [PricingPlanController::class, 'show'])->middleware('permission:pricing_plans.view');
        Route::put('/{pricingPlan}', [PricingPlanController::class, 'update'])->middleware('permission:pricing_plans.update');
        Route::delete('/{pricingPlan}', [PricingPlanController::class, 'destroy'])->middleware('permission:pricing_plans.delete');
    });

    Route::prefix('pricing-plan-rates')->group(function () {
        Route::get('/', [PricingPlanRateController::class, 'index'])->middleware('permission:pricing_plan_rates.view');
        Route::post('/', [PricingPlanRateController::class, 'store'])->middleware('permission:pricing_plan_rates.create');
        Route::get('/{pricingPlanRate}', [PricingPlanRateController::class, 'show'])->middleware('permission:pricing_plan_rates.view');
        Route::put('/{pricingPlanRate}', [PricingPlanRateController::class, 'update'])->middleware('permission:pricing_plan_rates.update');
        Route::delete('/{pricingPlanRate}', [PricingPlanRateController::class, 'destroy'])->middleware('permission:pricing_plan_rates.delete');
    });
});
