<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\Models\Order;
use App\Modules\Settings\Services\SystemSettingService;
use App\Modules\Warehouse\Enums\WarehouseChargeStatus;
use App\Modules\Warehouse\Enums\WarehouseChargeType;
use App\Modules\Warehouse\Models\WarehouseCharge;

class OrderPackagingChargeService
{
    public function __construct(
        private readonly SystemSettingService $systemSettingService,
    ) {}

    public function sync(Order $order, ?int $userId = null): ?WarehouseCharge
    {
        if (! $this->systemSettingService->get('packaging_fee_auto_charge', false)) {
            return null;
        }

        $order->loadMissing('merchant');

        $existingCharge = WarehouseCharge::query()
            ->where('order_id', $order->id)
            ->where('type', WarehouseChargeType::PACKAGING_PRODUCT)
            ->first();

        $totalPackagingFee = (float) $order->total_packaging_fee;

        if ($totalPackagingFee <= 0 || ! $order->requires_packaging) {
            if ($existingCharge !== null && $existingCharge->status !== WarehouseChargeStatus::CANCELLED) {
                $existingCharge->update([
                    'status' => WarehouseChargeStatus::CANCELLED,
                    'notes' => 'Packaging fee removed from order.',
                ]);
            }

            return $existingCharge;
        }

        $payload = [
            'merchant_id' => $order->merchant_id,
            'warehouse_id' => $order->stockReservations()->value('warehouse_id'),
            'order_id' => $order->id,
            'type' => WarehouseChargeType::PACKAGING_PRODUCT,
            'description' => 'Auto packaging charge for order '.$order->order_number,
            'quantity' => '1.00',
            'unit_price' => number_format($totalPackagingFee, 2, '.', ''),
            'amount' => number_format($totalPackagingFee, 2, '.', ''),
            'status' => WarehouseChargeStatus::PENDING,
            'charge_date' => now()->toDateString(),
            'notes' => $order->package_notes,
            'created_by' => $existingCharge?->created_by ?? $userId,
        ];

        if ($existingCharge !== null) {
            $existingCharge->update($payload);

            return $existingCharge->fresh();
        }

        return WarehouseCharge::create($payload);
    }
}
