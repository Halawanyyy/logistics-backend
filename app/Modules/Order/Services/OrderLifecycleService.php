<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Warehouse\Enums\WarehouseFulfillmentStatus;
use Illuminate\Http\Exceptions\HttpResponseException;

class OrderLifecycleService
{
    public function ensureCanModify(Order $order): void
    {
        if ($order->shipment()->exists()) {
            $this->fail('Order cannot be edited after shipment has been created.');
        }

        if ($this->isFinalOrderStatus((string) $order->status)) {
            $this->fail('Processed orders cannot be edited.');
        }

        if ($this->hasWarehouseProcessingStarted($order)) {
            $this->fail('Order cannot be edited after warehouse processing has started.');
        }
    }

    public function ensureCanDelete(Order $order): void
    {
        if ($order->shipment()->exists()) {
            $this->fail('Order cannot be deleted after shipment has been created.');
        }

        if ($this->isFinalOrderStatus((string) $order->status)) {
            $this->fail('Processed orders cannot be deleted.');
        }

        if ($this->hasWarehouseProcessingStarted($order)) {
            $this->fail('Order cannot be deleted after warehouse processing has started.');
        }
    }

    public function ensureShipmentCanBeCreated(Order $order): void
    {
        if ($order->status !== OrderStatus::CONFIRMED) {
            $this->fail('Only confirmed orders can be converted to shipment.');
        }

        if ((bool) $order->requires_review) {
            $this->fail('Order requires review before shipment creation.');
        }

        if (! $order->items()->exists()) {
            $this->fail('Order must have at least one item before shipment creation.');
        }

        if ($order->shipment()->exists()) {
            $this->fail('Shipment already exists for this order.');
        }

        if (
            $order->fulfillment_type === OrderFulfillmentType::FROM_WAREHOUSE
            && $order->warehouse_fulfillment_status !== WarehouseFulfillmentStatus::FULFILLED
        ) {
            $this->fail('Warehouse fulfillment orders can create shipments only after warehouse fulfillment is completed.');
        }
    }

    public function hasWarehouseProcessingStarted(Order $order): bool
    {
        if ($order->fulfillment_type !== OrderFulfillmentType::FROM_WAREHOUSE) {
            return false;
        }

        if ($order->stockReservations()->exists()) {
            return true;
        }

        return ! in_array((string) $order->warehouse_fulfillment_status, [
            '',
            WarehouseFulfillmentStatus::NOT_REQUIRED,
            WarehouseFulfillmentStatus::PENDING_RESERVATION,
        ], true);
    }

    private function isFinalOrderStatus(string $status): bool
    {
        return in_array($status, [
            OrderStatus::SHIPMENT_CREATED,
            OrderStatus::PARTIALLY_DELIVERED,
            OrderStatus::DELIVERED,
            OrderStatus::FAILED,
            OrderStatus::CANCELLED,
        ], true);
    }

    private function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
