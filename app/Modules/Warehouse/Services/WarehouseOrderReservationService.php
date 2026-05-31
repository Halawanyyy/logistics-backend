<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Enums\OrderItemDeliveryStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Warehouse\Enums\OrderStockReservationStatus;
use App\Modules\Warehouse\Enums\StockMovementType;
use App\Modules\Warehouse\Enums\WarehouseFulfillmentStatus;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\OrderStockReservation;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\WarehouseProduct;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class WarehouseOrderReservationService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly OrderWarehouseStatusService $orderWarehouseStatusService,
        private readonly WarehouseStockService $warehouseStockService,
    ) {
    }

    public function reserve(Order $order, array $data): Collection
    {
        return DB::transaction(function () use ($order, $data) {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->ensureOrderCanUseWarehouseFlow($lockedOrder);

            if ($lockedOrder->stockReservations()->where('status', OrderStockReservationStatus::RESERVED)->exists()) {
                $this->throwValidationError('Order already has active stock reservations.');
            }

            $reservations = new Collection();
            $userId = auth()->id();

            foreach ($data['items'] as $item) {
                $warehouseProduct = WarehouseProduct::query()->findOrFail($item['warehouse_product_id']);

                if ((int) $warehouseProduct->merchant_id !== (int) $lockedOrder->merchant_id) {
                    $this->throwValidationError('Warehouse product does not belong to the same merchant as the order.');
                }

                $stock = $this->warehouseStockService->lockStock(
                    (int) $data['warehouse_id'],
                    (int) $item['warehouse_product_id'],
                );

                if ($stock->quantity_available < (int) $item['quantity']) {
                    $this->throwValidationError('Not enough available stock.');
                }
                $snapshot = $this->warehouseStockService->applyDelta(
                    $stock,
                    availableDelta: -((int) $item['quantity']),
                    reservedDelta: (int) $item['quantity'],
                );

                $reservation = OrderStockReservation::create([
                    'order_id' => $lockedOrder->id,
                    'order_item_id' => $item['order_item_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'warehouse_product_id' => $item['warehouse_product_id'],
                    'quantity' => $item['quantity'],
                    'status' => OrderStockReservationStatus::RESERVED,
                    'notes' => $item['notes'] ?? null,
                    'created_by' => $userId,
                ]);

                $this->warehouseStockService->createMovement(
                    warehouseId: (int) $data['warehouse_id'],
                    warehouseProductId: (int) $item['warehouse_product_id'],
                    type: StockMovementType::RESERVED,
                    quantity: (int) $item['quantity'],
                    snapshot: $snapshot,
                    referenceType: 'order_stock_reservation',
                    referenceId: (int) $reservation->id,
                    notes: $reservation->notes,
                    userId: $userId,
                    orderId: (int) $lockedOrder->id,
                );

                $this->auditLogService->log(
                    AuditEventType::STOCK_RESERVED,
                    'reserved',
                    'warehouse_product',
                    (int) $item['warehouse_product_id'],
                    [
                        'user_id' => $userId,
                        'metadata' => [
                            'warehouse_id' => (int) $data['warehouse_id'],
                            'order_id' => $lockedOrder->id,
                            'reservation_id' => $reservation->id,
                            'quantity' => (int) $item['quantity'],
                        ],
                        'notes' => $reservation->notes,
                    ],
                );

                $reservations->push($reservation);

                $this->syncOrderItemReservationStatus($reservation->orderItem()->first());
            }

            $this->orderWarehouseStatusService->setStatus($lockedOrder, WarehouseFulfillmentStatus::RESERVED);

            return $reservations->load($this->reservationRelations());
        });
    }

    public function release(Order $order): Collection
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->ensureOrderCanUseWarehouseFlow($lockedOrder);

            $reservations = $lockedOrder->stockReservations()
                ->where('status', OrderStockReservationStatus::RESERVED)
                ->lockForUpdate()
                ->get();

            if ($reservations->isEmpty()) {
                $this->throwValidationError('No active stock reservation exists for this order.');
            }

            $userId = auth()->id();

            foreach ($reservations as $reservation) {
                $stock = $this->warehouseStockService->lockStock(
                    (int) $reservation->warehouse_id,
                    (int) $reservation->warehouse_product_id,
                );

                if ($stock->quantity_reserved < $reservation->quantity) {
                    $this->throwValidationError('Reserved stock is inconsistent.');
                }

                $snapshot = $this->warehouseStockService->applyDelta(
                    $stock,
                    availableDelta: (int) $reservation->quantity,
                    reservedDelta: -((int) $reservation->quantity),
                );

                $reservation->update([
                    'status' => OrderStockReservationStatus::RELEASED,
                    'released_at' => now(),
                ]);

                $this->warehouseStockService->createMovement(
                    warehouseId: (int) $reservation->warehouse_id,
                    warehouseProductId: (int) $reservation->warehouse_product_id,
                    type: StockMovementType::RELEASED,
                    quantity: (int) $reservation->quantity,
                    snapshot: $snapshot,
                    referenceType: 'order_stock_reservation',
                    referenceId: (int) $reservation->id,
                    notes: $reservation->notes,
                    userId: $userId,
                    orderId: (int) $lockedOrder->id,
                );

                $this->auditLogService->log(
                    AuditEventType::STOCK_RELEASED,
                    'released',
                    'warehouse_product',
                    (int) $reservation->warehouse_product_id,
                    [
                        'user_id' => $userId,
                        'metadata' => [
                            'warehouse_id' => (int) $reservation->warehouse_id,
                            'order_id' => $lockedOrder->id,
                            'reservation_id' => $reservation->id,
                            'quantity' => (int) $reservation->quantity,
                        ],
                        'notes' => $reservation->notes,
                    ],
                );

                $this->syncOrderItemReservationStatus($reservation->orderItem()->first());
            }

            $this->orderWarehouseStatusService->setStatus($lockedOrder, WarehouseFulfillmentStatus::PENDING_RESERVATION);

            return $reservations->load($this->reservationRelations());
        });
    }

    public function fulfill(Order $order): Collection
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->ensureOrderCanUseWarehouseFlow($lockedOrder);

            $this->orderWarehouseStatusService->ensureCurrentStatus(
                $lockedOrder,
                [
                    WarehouseFulfillmentStatus::RESERVED,
                    WarehouseFulfillmentStatus::PICKING,
                    WarehouseFulfillmentStatus::PICKED,
                    WarehouseFulfillmentStatus::PACKING,
                    WarehouseFulfillmentStatus::PACKED,
                    WarehouseFulfillmentStatus::READY_FOR_DISPATCH,
                ],
                'Order is not ready for warehouse fulfillment.',
            );

            $reservations = $lockedOrder->stockReservations()
                ->where('status', OrderStockReservationStatus::RESERVED)
                ->lockForUpdate()
                ->get();

            if ($reservations->isEmpty()) {
                $this->throwValidationError('No active stock reservation exists for this order.');
            }

            $userId = auth()->id();

            foreach ($reservations as $reservation) {
                $stock = $this->warehouseStockService->lockStock(
                    (int) $reservation->warehouse_id,
                    (int) $reservation->warehouse_product_id,
                );

                if ($stock->quantity_reserved < $reservation->quantity) {
                    $this->throwValidationError('Reserved stock is inconsistent.');
                }

                $snapshot = $this->warehouseStockService->applyDelta(
                    $stock,
                    reservedDelta: -((int) $reservation->quantity),
                );

                $reservation->update([
                    'status' => OrderStockReservationStatus::FULFILLED,
                    'fulfilled_at' => now(),
                ]);

                $this->warehouseStockService->createMovement(
                    warehouseId: (int) $reservation->warehouse_id,
                    warehouseProductId: (int) $reservation->warehouse_product_id,
                    type: StockMovementType::OUT,
                    quantity: (int) $reservation->quantity,
                    snapshot: $snapshot,
                    referenceType: 'order_stock_reservation',
                    referenceId: (int) $reservation->id,
                    notes: $reservation->notes,
                    userId: $userId,
                    orderId: (int) $lockedOrder->id,
                );

                $this->syncOrderItemReservationStatus($reservation->orderItem()->first());
            }

            $this->orderWarehouseStatusService->setStatus($lockedOrder, WarehouseFulfillmentStatus::FULFILLED);

            return $reservations->load($this->reservationRelations());
        });
    }

    private function ensureOrderCanUseWarehouseFlow(Order $order): void
    {
        if ($order->fulfillment_type !== OrderFulfillmentType::FROM_WAREHOUSE) {
            $this->throwValidationError('Only warehouse fulfillment orders can reserve stock.');
        }

        if ($order->status === OrderStatus::CANCELLED) {
            $this->throwValidationError('Cancelled orders cannot manage warehouse stock.');
        }

        if ($order->warehouse_fulfillment_status === WarehouseFulfillmentStatus::FULFILLED) {
            $this->throwValidationError('Fulfilled warehouse orders cannot manage warehouse stock again.');
        }
    }

    private function syncOrderItemReservationStatus(?OrderItem $orderItem): void
    {
        if ($orderItem === null) {
            return;
        }

        if ((int) $orderItem->delivered_quantity > 0 || (int) $orderItem->returned_quantity > 0 || (int) $orderItem->damaged_quantity > 0) {
            return;
        }

        if ($orderItem->stockReservations()->where('status', OrderStockReservationStatus::FULFILLED)->exists()) {
            $orderItem->update(['delivery_status' => OrderItemDeliveryStatus::FULFILLED]);
            return;
        }

        if ($orderItem->stockReservations()->where('status', OrderStockReservationStatus::RESERVED)->exists()) {
            $orderItem->update(['delivery_status' => OrderItemDeliveryStatus::RESERVED]);
            return;
        }

        $orderItem->update(['delivery_status' => OrderItemDeliveryStatus::PENDING]);
    }

    /**
     * @return list<string>
     */
    private function reservationRelations(): array
    {
        return [
            'warehouse',
            'warehouseProduct.merchant',
            'createdBy',
        ];
    }

    private function throwValidationError(string $message): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
