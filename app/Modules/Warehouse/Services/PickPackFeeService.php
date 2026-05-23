<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Order\Models\Order;
use App\Modules\Warehouse\Enums\WarehouseChargeType;
use App\Modules\Warehouse\Models\PickPackFeeRule;
use App\Modules\Warehouse\Models\WarehouseCharge;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;

class PickPackFeeService
{
    public function __construct(
        private readonly WarehouseChargeService $warehouseChargeService,
    ) {
    }

    /**
     * @return Collection<int, WarehouseCharge>
     */
    public function generate(Order $order, ?int $userId): Collection
    {
        $rule = $this->resolveRule($order);

        if ($rule === null) {
            $this->fail('No active pick and pack fee rule found for this order.');
        }

        $existingAutoCharges = WarehouseCharge::query()
            ->where('order_id', $order->id)
            ->whereIn('description', ['Auto-generated pick fee', 'Auto-generated pack fee'])
            ->exists();

        if ($existingAutoCharges) {
            $this->fail('Pick and pack fees have already been generated for this order.');
        }

        $totalItems = max((int) $order->items()->sum('quantity'), 0);

        if ($totalItems === 0) {
            $this->fail('Order must have items before pick and pack fees can be generated.');
        }

        $pickFee = (float) $rule->pick_fee_first_item;

        if ($totalItems > 1) {
            $pickFee += ($totalItems - 1) * (float) $rule->pick_fee_additional_item;
        }

        $packFee = (float) $rule->packing_fee_base;

        if ((bool) $order->is_fragile) {
            $packFee += (float) $rule->fragile_extra_fee;
        }

        $warehouseId = $order->stockReservations()->value('warehouse_id');
        $charges = collect();

        if ($pickFee > 0) {
            $charges->push($this->warehouseChargeService->store([
                'merchant_id' => $order->merchant_id,
                'warehouse_id' => $warehouseId,
                'order_id' => $order->id,
                'shipment_id' => $order->shipment?->id,
                'type' => WarehouseChargeType::FULFILLMENT,
                'description' => 'Auto-generated pick fee',
                'quantity' => 1,
                'unit_price' => $pickFee,
            ], $userId));
        }

        if ($packFee > 0) {
            $charges->push($this->warehouseChargeService->store([
                'merchant_id' => $order->merchant_id,
                'warehouse_id' => $warehouseId,
                'order_id' => $order->id,
                'shipment_id' => $order->shipment?->id,
                'type' => WarehouseChargeType::PACKAGING,
                'description' => 'Auto-generated pack fee',
                'quantity' => 1,
                'unit_price' => $packFee,
            ], $userId));
        }

        return $charges;
    }

    private function resolveRule(Order $order): ?PickPackFeeRule
    {
        return PickPackFeeRule::query()
            ->where('status', 'active')
            ->where('merchant_id', $order->merchant_id)
            ->latest()
            ->first()
            ?? PickPackFeeRule::query()
                ->where('status', 'active')
                ->whereNull('merchant_id')
                ->where('pricing_plan_id', $order->merchant?->pricing_plan_id)
                ->latest()
                ->first();
    }

    private function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
