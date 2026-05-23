<?php

namespace App\Modules\LocationPricing\Controllers;

use App\Modules\LocationPricing\Enums\PricingPlanStatus;
use App\Modules\LocationPricing\Models\PricingPlan;
use App\Modules\LocationPricing\Requests\StorePricingPlanRequest;
use App\Modules\LocationPricing\Requests\UpdatePricingPlanRequest;
use App\Modules\LocationPricing\Resources\PricingPlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PricingPlanController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(PricingPlanStatus::values())],
        ]);

        $pricingPlans = PricingPlan::query()
            ->withCount('merchants')
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->latest()
            ->get();

        return PricingPlanResource::collection($pricingPlans);
    }

    public function store(StorePricingPlanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $pricingPlan = PricingPlan::query()->create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'monthly_orders_from' => $validated['monthly_orders_from'] ?? null,
            'monthly_orders_to' => $validated['monthly_orders_to'] ?? null,
            'status' => $validated['status'] ?? PricingPlanStatus::ACTIVE,
            'created_by' => $request->user()?->id,
        ]);

        return (new PricingPlanResource($pricingPlan->loadCount('merchants')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PricingPlan $pricingPlan): PricingPlanResource
    {
        return new PricingPlanResource($pricingPlan->loadCount('merchants'));
    }

    public function update(UpdatePricingPlanRequest $request, PricingPlan $pricingPlan): PricingPlanResource
    {
        $pricingPlan->update($request->validated());

        return new PricingPlanResource($pricingPlan->fresh()->loadCount('merchants'));
    }

    public function destroy(PricingPlan $pricingPlan): JsonResponse
    {
        if ($pricingPlan->merchants()->exists()) {
            return response()->json([
                'message' => 'Pricing plan cannot be deleted while assigned to merchants.',
            ], 422);
        }

        $pricingPlan->delete();

        return response()->json([
            'message' => 'Pricing plan deleted successfully',
        ]);
    }
}
