<?php

namespace App\Modules\Warehouse\Controllers;

use App\Modules\Warehouse\Enums\StorageFeeCalculationType;
use App\Modules\Warehouse\Models\StorageFeeRule;
use App\Modules\Warehouse\Resources\StorageFeeRuleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class StorageFeeRuleController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'merchant_id' => ['nullable', Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'pricing_plan_id' => ['nullable', Rule::exists('pricing_plans', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'status' => ['nullable', 'string'],
        ]);

        $rules = StorageFeeRule::query()
            ->with(['merchant', 'pricingPlan'])
            ->when(isset($validated['merchant_id']), fn ($query) => $query->where('merchant_id', $validated['merchant_id']))
            ->when(isset($validated['pricing_plan_id']), fn ($query) => $query->where('pricing_plan_id', $validated['pricing_plan_id']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->latest()
            ->get();

        return StorageFeeRuleResource::collection($rules);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'merchant_id' => ['nullable', Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'pricing_plan_id' => ['nullable', Rule::exists('pricing_plans', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'name' => ['required', 'string', 'max:255'],
            'calculation_type' => ['required', Rule::in(StorageFeeCalculationType::values())],
            'free_units' => ['nullable', 'integer', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string'],
        ]);

        $rule = StorageFeeRule::create([
            ...$validated,
            'free_units' => $validated['free_units'] ?? 0,
            'unit_price' => $validated['unit_price'] ?? 0,
            'status' => $validated['status'] ?? 'active',
        ]);

        return (new StorageFeeRuleResource($rule->load(['merchant', 'pricingPlan'])))->response()->setStatusCode(201);
    }

    public function show(StorageFeeRule $storageFeeRule): StorageFeeRuleResource
    {
        return new StorageFeeRuleResource($storageFeeRule->load(['merchant', 'pricingPlan']));
    }

    public function update(Request $request, StorageFeeRule $storageFeeRule): StorageFeeRuleResource
    {
        $validated = $request->validate([
            'merchant_id' => ['sometimes', 'nullable', Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'pricing_plan_id' => ['sometimes', 'nullable', Rule::exists('pricing_plans', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'name' => ['sometimes', 'string', 'max:255'],
            'calculation_type' => ['sometimes', Rule::in(StorageFeeCalculationType::values())],
            'free_units' => ['sometimes', 'integer', 'min:0'],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $storageFeeRule->update($validated);

        return new StorageFeeRuleResource($storageFeeRule->fresh()->load(['merchant', 'pricingPlan']));
    }

    public function destroy(StorageFeeRule $storageFeeRule): JsonResponse
    {
        $storageFeeRule->delete();

        return response()->json(['message' => 'Storage fee rule deleted successfully']);
    }
}
