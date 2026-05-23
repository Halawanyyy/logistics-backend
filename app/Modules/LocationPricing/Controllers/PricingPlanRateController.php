<?php

namespace App\Modules\LocationPricing\Controllers;

use App\Modules\LocationPricing\Models\PricingPlanRate;
use App\Modules\LocationPricing\Requests\ListPricingPlanRatesRequest;
use App\Modules\LocationPricing\Requests\StorePricingPlanRateRequest;
use App\Modules\LocationPricing\Requests\UpdatePricingPlanRateRequest;
use App\Modules\LocationPricing\Resources\PricingPlanRateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PricingPlanRateController
{
    public function index(ListPricingPlanRatesRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $pricingPlanRates = PricingPlanRate::query()
            ->with(['pricingPlan', 'governorate', 'area'])
            ->when(isset($validated['pricing_plan_id']), fn ($query) => $query->where('pricing_plan_id', $validated['pricing_plan_id']))
            ->when(isset($validated['governorate_id']), fn ($query) => $query->where('governorate_id', $validated['governorate_id']))
            ->when(array_key_exists('area_id', $validated), function ($query) use ($validated) {
                if ($validated['area_id'] === null) {
                    return $query->whereNull('area_id');
                }

                return $query->where('area_id', $validated['area_id']);
            })
            ->latest()
            ->get();

        return PricingPlanRateResource::collection($pricingPlanRates);
    }

    public function store(StorePricingPlanRateRequest $request): JsonResponse
    {
        $pricingPlanRate = PricingPlanRate::query()->create([
            'pricing_plan_id' => $request->integer('pricing_plan_id'),
            'governorate_id' => $request->integer('governorate_id'),
            'area_id' => $request->input('area_id'),
            'delivery_fee' => $request->input('delivery_fee'),
            'swap_fee' => $request->input('swap_fee', 0),
            'return_fee' => $request->input('return_fee', 0),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return (new PricingPlanRateResource($pricingPlanRate->load(['pricingPlan', 'governorate', 'area'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PricingPlanRate $pricingPlanRate): PricingPlanRateResource
    {
        return new PricingPlanRateResource($pricingPlanRate->load(['pricingPlan', 'governorate', 'area']));
    }

    public function update(UpdatePricingPlanRateRequest $request, PricingPlanRate $pricingPlanRate): PricingPlanRateResource
    {
        $validated = $request->validated();

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $pricingPlanRate->update($validated);

        return new PricingPlanRateResource($pricingPlanRate->fresh()->load(['pricingPlan', 'governorate', 'area']));
    }

    public function destroy(PricingPlanRate $pricingPlanRate): JsonResponse
    {
        $pricingPlanRate->delete();

        return response()->json([
            'message' => 'Pricing plan rate deleted successfully',
        ]);
    }
}
