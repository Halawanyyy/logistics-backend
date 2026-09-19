<?php

namespace App\Modules\LocationPricing\Controllers;

use App\Modules\LocationPricing\Enums\PricingPlanStatus;
use App\Modules\LocationPricing\Models\PricingPlan;
use App\Modules\LocationPricing\Requests\StorePricingPlanRequest;
use App\Modules\LocationPricing\Requests\UpdatePricingPlanRequest;
use App\Modules\LocationPricing\Resources\PricingPlanResource;
use App\Modules\LocationPricing\Services\PricingPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PricingPlanController
{
    public function __construct(
        private readonly PricingPlanService $pricingPlanService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(PricingPlanStatus::values())],
        ]);

        $pricingPlans = PricingPlan::query()
            ->with(['features'])
            ->withCount('merchants')
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->orderBy('display_order')
            ->latest('id')
            ->get();

        return PricingPlanResource::collection($pricingPlans);
    }

    public function store(StorePricingPlanRequest $request): JsonResponse
    {
        $pricingPlan = $this->pricingPlanService->create($request->validated(), $request->user());

        return (new PricingPlanResource($pricingPlan))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PricingPlan $pricingPlan): PricingPlanResource
    {
        return new PricingPlanResource($pricingPlan->load(['features'])->loadCount('merchants'));
    }

    public function update(UpdatePricingPlanRequest $request, PricingPlan $pricingPlan): PricingPlanResource
    {
        $updated = $this->pricingPlanService->update($pricingPlan, $request->validated());

        return new PricingPlanResource($updated);
    }

    public function destroy(PricingPlan $pricingPlan): JsonResponse
    {
        $this->pricingPlanService->delete($pricingPlan);

        return response()->json([
            'message' => 'Pricing plan deleted successfully',
        ]);
    }
}
