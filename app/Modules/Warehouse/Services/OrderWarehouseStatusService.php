<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Models\Order;
use App\Modules\Warehouse\Enums\WarehouseFulfillmentStatus;
use Illuminate\Http\Exceptions\HttpResponseException;

class OrderWarehouseStatusService
{
    public function defaultStatusForFulfillment(string $fulfillmentType): string
    {
        return $fulfillmentType === OrderFulfillmentType::FROM_WAREHOUSE
            ? WarehouseFulfillmentStatus::PENDING_RESERVATION
            : WarehouseFulfillmentStatus::NOT_REQUIRED;
    }

    public function ensureWarehouseOrder(Order $order, string $message = 'Only warehouse fulfillment orders can use warehouse flow.'): void
    {
        if ($order->fulfillment_type !== OrderFulfillmentType::FROM_WAREHOUSE) {
            throw new HttpResponseException(response()->json([
                'message' => $message,
            ], 422));
        }
    }

    public function ensureCurrentStatus(Order $order, array $allowedStatuses, string $message): void
    {
        if (! in_array($order->warehouse_fulfillment_status, $allowedStatuses, true)) {
            throw new HttpResponseException(response()->json([
                'message' => $message,
            ], 422));
        }
    }

    public function setStatus(Order $order, string $status): Order
    {
        $order->update([
            'warehouse_fulfillment_status' => $status,
        ]);

        return $order->fresh();
    }
}
