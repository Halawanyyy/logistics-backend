<?php

namespace App\Modules\Warehouse\Controllers;

use App\Models\Merchant;
use App\Modules\Order\Models\Order;
use App\Modules\Warehouse\Resources\WarehouseChargeResource;
use App\Modules\Warehouse\Services\PickPackFeeService;
use App\Modules\Warehouse\Services\StorageFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseBillingAutomationController
{
    public function __construct(
        private readonly StorageFeeService $storageFeeService,
        private readonly PickPackFeeService $pickPackFeeService,
    ) {
    }

    public function generateStorageFees(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'merchant_id' => ['required', Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $summary = $this->storageFeeService->generate(
            Merchant::query()->with('pricingPlan')->findOrFail($validated['merchant_id']),
            $validated['period_start'],
            $validated['period_end'],
            $request->user()?->id,
        );

        return response()->json([
            'data' => [
                ...$summary,
                'charge' => (new WarehouseChargeResource($summary['charge']))->resolve($request),
            ],
        ]);
    }

    public function generatePickPackFees(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', Rule::exists('orders', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
        ]);

        $order = Order::query()->with(['items', 'merchant', 'shipment'])->findOrFail($validated['order_id']);
        $charges = $this->pickPackFeeService->generate($order, $request->user()?->id);

        return response()->json([
            'data' => WarehouseChargeResource::collection($charges->load(['merchant', 'warehouse', 'warehouseProduct', 'createdBy']))->resolve($request),
        ]);
    }
}
