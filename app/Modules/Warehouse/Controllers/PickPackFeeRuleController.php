<?php

namespace App\Modules\Warehouse\Controllers;

use App\Modules\Warehouse\Models\PickPackFeeRule;
use App\Modules\Warehouse\Resources\PickPackFeeRuleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PickPackFeeRuleController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'merchant_id' => ['nullable', Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'pricing_plan_id' => ['nullable', Rule::exists('pricing_plans', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'status' => ['nullable', 'string'],
        ]);

        $rules = PickPackFeeRule::query()
            ->with(['merchant', 'pricingPlan'])
            ->when(isset($validated['merchant_id']), fn ($query) => $query->where('merchant_id', $validated['merchant_id']))
            ->when(isset($validated['pricing_plan_id']), fn ($query) => $query->where('pricing_plan_id', $validated['pricing_plan_id']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->latest()
            ->get();

        return PickPackFeeRuleResource::collection($rules);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'merchant_id' => ['nullable', Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'pricing_plan_id' => ['nullable', Rule::exists('pricing_plans', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'name' => ['required', 'string', 'max:255'],
            'pick_fee_first_item' => ['nullable', 'numeric', 'min:0'],
            'pick_fee_additional_item' => ['nullable', 'numeric', 'min:0'],
            'packing_fee_base' => ['nullable', 'numeric', 'min:0'],
            'fragile_extra_fee' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string'],
        ]);

        $rule = PickPackFeeRule::create([
            ...$validated,
            'pick_fee_first_item' => $validated['pick_fee_first_item'] ?? 0,
            'pick_fee_additional_item' => $validated['pick_fee_additional_item'] ?? 0,
            'packing_fee_base' => $validated['packing_fee_base'] ?? 0,
            'fragile_extra_fee' => $validated['fragile_extra_fee'] ?? 0,
            'status' => $validated['status'] ?? 'active',
        ]);

        return (new PickPackFeeRuleResource($rule->load(['merchant', 'pricingPlan'])))->response()->setStatusCode(201);
    }

    public function show(PickPackFeeRule $pickPackFeeRule): PickPackFeeRuleResource
    {
        return new PickPackFeeRuleResource($pickPackFeeRule->load(['merchant', 'pricingPlan']));
    }

    public function update(Request $request, PickPackFeeRule $pickPackFeeRule): PickPackFeeRuleResource
    {
        $validated = $request->validate([
            'merchant_id' => ['sometimes', 'nullable', Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'pricing_plan_id' => ['sometimes', 'nullable', Rule::exists('pricing_plans', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'name' => ['sometimes', 'string', 'max:255'],
            'pick_fee_first_item' => ['sometimes', 'numeric', 'min:0'],
            'pick_fee_additional_item' => ['sometimes', 'numeric', 'min:0'],
            'packing_fee_base' => ['sometimes', 'numeric', 'min:0'],
            'fragile_extra_fee' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $pickPackFeeRule->update($validated);

        return new PickPackFeeRuleResource($pickPackFeeRule->fresh()->load(['merchant', 'pricingPlan']));
    }

    public function destroy(PickPackFeeRule $pickPackFeeRule): JsonResponse
    {
        $pickPackFeeRule->delete();

        return response()->json(['message' => 'Pick and pack fee rule deleted successfully']);
    }
}
