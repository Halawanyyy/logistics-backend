<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\LocationPricing\Enums\PricingPlanStatus;
use App\Modules\LocationPricing\Models\PricingPlan;
use App\Modules\LocationPricing\Resources\PricingPlanResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\Settings\Services\SystemSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly SystemSettingService $systemSettingService,
    ) {
    }

    public function plans(Request $request): JsonResponse
    {
        $merchant = $this->merchantPortalAccessService->currentMerchant($request);

        $plans = PricingPlan::query()
            ->with(['features'])
            ->where('status', PricingPlanStatus::ACTIVE)
            ->where('is_visible_to_merchants', true)
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'data' => [
                'plans' => PricingPlanResource::collection($plans),
                'current_plan_id' => $merchant->pricing_plan_id,
                'pricing_contact_email' => $this->systemSettingService->getPricingContactEmail(),
            ],
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pricing_plan_id' => ['required', 'exists:pricing_plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $plan = PricingPlan::query()
            ->with(['features'])
            ->whereKey($validated['pricing_plan_id'])
            ->where('status', PricingPlanStatus::ACTIVE)
            ->where('is_visible_to_merchants', true)
            ->firstOrFail();

        if ($plan->requires_contact || $plan->is_custom_pricing) {
            return response()->json([
                'message' => 'Custom pricing plans require contacting support.',
            ], 422);
        }

        $merchant = $this->merchantPortalAccessService->currentMerchant($request);
        $merchant->update([
            'pricing_plan_id' => $plan->id,
        ]);

        return response()->json([
            'message' => 'Subscription updated successfully.',
            'data' => new PricingPlanResource($plan),
        ]);
    }
}
