<?php

namespace App\Modules\CompanyCatalog\Controllers;

use App\Models\Merchant;
use App\Modules\CompanyCatalog\Requests\MerchantShopCatalogRequest;
use App\Modules\CompanyCatalog\Requests\MerchantShopPricingRequest;
use App\Modules\CompanyCatalog\Services\MerchantShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantShopController
{
    public function __construct(
        private readonly MerchantShopService $merchantShopService,
    ) {}

    public function catalog(MerchantShopCatalogRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->merchantShopService->catalog($request->validated()),
        ]);
    }

    public function pricing(MerchantShopPricingRequest $request): JsonResponse
    {
        $merchant = null;
        $user = $request->user();

        if ($request->filled('merchant_id')) {
            $merchant = Merchant::query()->with('pricingPlan')->findOrFail((int) $request->input('merchant_id'));
        } elseif ($user !== null && method_exists($user, 'activeMerchantUser')) {
            $merchant = $user->activeMerchantUser()->with('merchant.pricingPlan')->first()?->merchant;
        }

        return response()->json(
            $this->merchantShopService->pricing($merchant, $request->validated())
        );
    }

    public function preview(Request $request, Merchant $merchant): JsonResponse
    {
        return response()->json(
            $this->merchantShopService->pricing(
                $merchant->load('pricingPlan'),
                $request->validate([]),
                $request->user(),
                true,
            )
        );
    }
}
