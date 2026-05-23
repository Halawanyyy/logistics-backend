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

                $stock = $this->lockInventoryStock(
                    (int) $data['warehouse_id'],
                    (int) $item['warehouse_product_id'],
                );

                if ($stock->quantity_available < (int) $item['quantity']) {
                    $this->throwValidationError('Not enough available stock.');
                }

                $beforeAvailable = $stock->quantity_available;
                $beforeReserved = $stock->quantity_reserved;
                $beforeDamaged = $stock->quantity_damaged;

                $stock->update([
                    'quantity_available' => $beforeAvailable - (int) $item['quantity'],
                    'quantity_reserved' => $beforeReserved + (int) $item['quantity'],
                ]);

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

                $this->createStockMovement(
                    warehouseId: (int) $data['warehouse_id'],
                    warehouseProductId: (int) $item['warehouse_product_id'],
                    type: StockMovementType::RESERVED,
                    quantity: (int) $item['quantity'],
                    beforeAvailable: $beforeAvailable,
                    afterAvailable: $stock->quantity_available,
                    beforeReserved: $beforeReserved,
                    afterReserved: $stock->quantity_reserved,
                    beforeDamaged: $beforeDamaged,
                    afterDamaged: $stock->quantity_damaged,
                    notes: $reservation->notes,
                    referenceId: $reservation->id,
                    userId: $userId,
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

            $userId = auth()->id();

            foreach ($reservations as $reservation) {
                $stock = $this->lockInventoryStock(
                    (int) $reservation->warehouse_id,
                    (int) $reservation->warehouse_product_id,
                );

                if ($stock->quantity_reserved < $reservation->quantity) {
                    $this->throwValidationError('Reserved stock is inconsistent.');
                }

                $beforeAvailable = $stock->quantity_available;
                $beforeReserved = $stock->quantity_reserved;
                $beforeDamaged = $stock->quantity_damaged;

                $stock->update([
                    'quantity_available' => $beforeAvailable + $reservation->quantity,
                    'quantity_reserved' => $beforeReserved - $reservation->quantity,
                ]);

                $reservation->update([
                    'status' => OrderStockReservationStatus::RELEASED,
                    'released_at' => now(),
                ]);

                $this->createStockMovement(
                    warehouseId: (int) $reservation->warehouse_id,
                    warehouseProductId: (int) $reservation->warehouse_product_id,
                    type: StockMovementType::RELEASED,
                    quantity: (int) $reservation->quantity,
                    beforeAvailable: $beforeAvailable,
                    afterAvailable: $stock->quantity_available,
                    beforeReserved: $beforeReserved,
                    afterReserved: $stock->quantity_reserved,
                    beforeDamaged: $beforeDamaged,
                    afterDamaged: $stock->quantity_damaged,
                    notes: $reservation->notes,
                    referenceId: (int) $reservation->id,
                    userId: $userId,
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

            $reservations = $lockedOrder->stockReservations()
                ->where('status', OrderStockReservationStatus::RESERVED)
                ->lockForUpdate()
                ->get();

            $userId = auth()->id();

            foreach ($reservations as $reservation) {
                $stock = $this->lockInventoryStock(
                    (int) $reservation->warehouse_id,
                    (int) $reservation->warehouse_product_id,
                );

                if ($stock->quantity_reserved < $reservation->quantity) {
                    $this->throwValidationError('Reserved stock is inconsistent.');
                }

                $beforeAvailable = $stock->quantity_available;
                $beforeReserved = $stock->quantity_reserved;
                $beforeDamaged = $stock->quantity_damaged;

                $stock->update([
                    'quantity_reserved' => $beforeReserved - $reservation->quantity,
                ]);

                $reservation->update([
                    'status' => OrderStockReservationStatus::FULFILLED,
                    'fulfilled_at' => now(),
                ]);

                $this->createStockMovement(
                    warehouseId: (int) $reservation->warehouse_id,
                    warehouseProductId: (int) $reservation->warehouse_product_id,
                    type: StockMovementType::OUT,
                    quantity: (int) $reservation->quantity,
                    beforeAvailable: $beforeAvailable,
                    afterAvailable: $stock->quantity_available,
                    beforeReserved: $beforeReserved,
                    afterReserved: $stock->quantity_reserved,
                    beforeDamaged: $beforeDamaged,
                    afterDamaged: $stock->quantity_damaged,
                    notes: $reservation->notes,
                    referenceId: (int) $reservation->id,
                    userId: $userId,
                );

                $this->syncOrderItemReservationStatus($reservation->orderItem()->first());
            }

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
    }

    private function lockInventoryStock(int $warehouseId, int $warehouseProductId): InventoryStock
    {
        return InventoryStock::query()
            ->lockForUpdate()
            ->firstOrCreate(
                [
                    'warehouse_id' => $warehouseId,
                    'warehouse_product_id' => $warehouseProductId,
                ],
                [
                    'quantity_available' => 0,
                    'quantity_reserved' => 0,
                    'quantity_damaged' => 0,
                ]
            );
    }

    private function createStockMovement(
        int $warehouseId,
        int $warehouseProductId,
        string $type,
        int $quantity,
        int $beforeAvailable,
        int $afterAvailable,
        int $beforeReserved,
        int $afterReserved,
        int $beforeDamaged,
        int $afterDamaged,
        ?string $notes,
        int $referenceId,
        ?int $userId,
    ): void {
        StockMovement::create([
            'warehouse_id' => $warehouseId,
            'warehouse_product_id' => $warehouseProductId,
            'type' => $type,
            'quantity' => $quantity,
            'before_available' => $beforeAvailable,
            'after_available' => $afterAvailable,
            'before_reserved' => $beforeReserved,
            'after_reserved' => $afterReserved,
            'before_damaged' => $beforeDamaged,
            'after_damaged' => $afterDamaged,
            'reference_type' => 'order_stock_reservation',
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $userId,
        ]);
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
