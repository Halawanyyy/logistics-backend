<?php

namespace App\Modules\Shipment\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Enums\OrderItemDeliveryStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Finance\Services\MerchantWalletService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Settings\Services\SystemSettingService;
use App\Modules\Shipment\Enums\ShipmentItemResultStatus;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Shipment\Models\ShipmentItemResult;
use App\Modules\Warehouse\Enums\OrderStockReservationStatus;
use App\Modules\Warehouse\Enums\StockMovementType;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\OrderStockReservation;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Services\WarehouseReturnService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class ShipmentDeliveryCompletionService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly WarehouseReturnService $warehouseReturnService,
        private readonly MerchantWalletService $merchantWalletService,
        private readonly SystemSettingService $systemSettingService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Shipment $shipment, array $data, ?int $userId): Shipment
    {
        return DB::transaction(function () use ($shipment, $data, $userId) {
            $lockedShipment = Shipment::query()
                ->with(['order'])
                ->lockForUpdate()
                ->findOrFail($shipment->id);

            $this->ensureShipmentCanBeCompleted($lockedShipment);

            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($lockedShipment->order_id);

            $orderItems = OrderItem::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $existingCancelledQuantities = ShipmentItemResult::query()
                ->selectRaw('order_item_id, SUM(quantity) as total_quantity')
                ->whereIn('order_item_id', $orderItems->keys())
                ->where('result_status', ShipmentItemResultStatus::CANCELLED)
                ->groupBy('order_item_id')
                ->pluck('total_quantity', 'order_item_id');

            $requestCancelledQuantities = [];

            foreach ($data['items'] as $itemData) {
                $orderItemId = (int) $itemData['order_item_id'];
                /** @var OrderItem|null $orderItem */
                $orderItem = $orderItems->get($orderItemId);

                if ($orderItem === null) {
                    $this->fail('Selected order item does not belong to this shipment order.');
                }

                $shipmentItemResult = ShipmentItemResult::create([
                    'shipment_id' => $lockedShipment->id,
                    'order_item_id' => $orderItem->id,
                    'warehouse_product_id' => $itemData['warehouse_product_id'] ?? null,
                    'result_status' => $itemData['result_status'],
                    'quantity' => (int) $itemData['quantity'],
                    'return_condition' => $itemData['return_condition'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                    'created_by' => $userId,
                ]);

                $quantity = (int) $itemData['quantity'];

                switch ($itemData['result_status']) {
                    case ShipmentItemResultStatus::DELIVERED:
                        $orderItem->delivered_quantity += $quantity;
                        break;

                    case ShipmentItemResultStatus::RETURNED:
                        $orderItem->returned_quantity += $quantity;
                        $this->handleReturnedItem(
                            shipment: $lockedShipment,
                            order: $order,
                            orderItem: $orderItem,
                            shipmentItemResult: $shipmentItemResult,
                            itemData: $itemData,
                            quantity: $quantity,
                            userId: $userId,
                        );
                        break;

                    case ShipmentItemResultStatus::DAMAGED:
                        $orderItem->damaged_quantity += $quantity;
                        $this->handleDamagedItem(
                            orderItem: $orderItem,
                            shipmentItemResult: $shipmentItemResult,
                            itemData: $itemData,
                            quantity: $quantity,
                            userId: $userId,
                        );
                        break;

                    case ShipmentItemResultStatus::CANCELLED:
                        $requestCancelledQuantities[$orderItem->id] = ($requestCancelledQuantities[$orderItem->id] ?? 0) + $quantity;
                        break;
                }

                $cancelledQuantity = (int) ($existingCancelledQuantities[$orderItem->id] ?? 0) + (int) ($requestCancelledQuantities[$orderItem->id] ?? 0);
                $orderItem->delivery_status = $this->resolveOrderItemDeliveryStatus($orderItem, $cancelledQuantity);
                $orderItem->save();
            }

            $finalShipmentStatus = $this->determineFinalShipmentStatus(
                $orderItems->values()->all(),
                $existingCancelledQuantities->all(),
                $requestCancelledQuantities,
            );

            if ($finalShipmentStatus !== $data['shipment_status']) {
                $this->fail('The provided shipment status does not match the item delivery results.');
            }

            if (
                $finalShipmentStatus === ShipmentStatus::PARTIALLY_DELIVERED
                && ! (bool) $this->systemSettingService->get('allow_partial_delivery', true)
            ) {
                $this->fail('Partial delivery is disabled by system settings.');
            }

            $oldShipmentStatus = $lockedShipment->status;
            $lockedShipment->update([
                'status' => $finalShipmentStatus,
            ]);

            $lockedShipment->histories()->create([
                'status' => $finalShipmentStatus,
                'notes' => $data['notes'] ?? null,
                'changed_by' => $userId,
            ]);

            $oldOrderStatus = $order->status;
            $order->update([
                'status' => $this->mapOrderStatus($finalShipmentStatus),
            ]);

            $this->auditLogService->logStatusChange(
                $lockedShipment->fresh(),
                AuditEventType::SHIPMENT_STATUS_UPDATED,
                $oldShipmentStatus,
                $finalShipmentStatus,
                [
                    'user_id' => $userId,
                ],
                $data['notes'] ?? null,
            );

            if ($finalShipmentStatus === ShipmentStatus::PARTIALLY_DELIVERED) {
                $this->auditLogService->log(
                    AuditEventType::PARTIAL_DELIVERY_COMPLETED,
                    'completed',
                    $lockedShipment->fresh(),
                    null,
                    [
                        'user_id' => $userId,
                        'metadata' => [
                            'order_id' => $order->id,
                            'order_status_before' => $oldOrderStatus,
                            'order_status_after' => $order->status,
                            'items_count' => count($data['items']),
                        ],
                        'notes' => $data['notes'] ?? 'Partial delivery completed.',
                    ],
                );
            }

            $this->merchantWalletService->syncShipmentTransactions(
                $lockedShipment->fresh()->load(['order']),
                $userId
            );

            return $lockedShipment->fresh()->load([
                'merchant',
                'order',
                'order.items',
                'deliveryGovernorate',
                'deliveryArea',
                'assignedDriver',
                'histories',
                'histories.changedBy',
                'itemResults',
                'itemResults.orderItem',
            ]);
        });
    }

    private function ensureShipmentCanBeCompleted(Shipment $shipment): void
    {
        if (in_array($shipment->status, [
            ShipmentStatus::DELIVERED,
            ShipmentStatus::PARTIALLY_DELIVERED,
            ShipmentStatus::FAILED,
            ShipmentStatus::RETURNED,
            ShipmentStatus::RETURNED_TO_HUB,
            ShipmentStatus::RETURNED_TO_WAREHOUSE,
            ShipmentStatus::CANCELLED,
        ], true)) {
            $this->fail('This shipment has already been completed.');
        }
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    private function handleReturnedItem(
        Shipment $shipment,
        Order $order,
        OrderItem $orderItem,
        ShipmentItemResult $shipmentItemResult,
        array $itemData,
        int $quantity,
        ?int $userId,
    ): void {
        $warehouseProductId = $itemData['warehouse_product_id'] ?? null;

        if ($shipment->order?->fulfillment_type !== OrderFulfillmentType::FROM_WAREHOUSE || $warehouseProductId === null) {
            return;
        }

        $reservation = $this->resolveReservationForWarehouseProduct($orderItem, (int) $warehouseProductId);

        if ($reservation === null) {
            $this->fail('Unable to resolve warehouse reservation for the returned item.');
        }

        $this->warehouseReturnService->store([
            'shipment_id' => $shipment->id,
            'order_id' => $order->id,
            'warehouse_id' => $reservation->warehouse_id,
            'warehouse_product_id' => (int) $warehouseProductId,
            'quantity' => $quantity,
            'condition' => $itemData['return_condition'],
            'notes' => $itemData['notes'] ?? null,
            'defer_processing' => true,
        ], $userId);
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    private function handleDamagedItem(
        OrderItem $orderItem,
        ShipmentItemResult $shipmentItemResult,
        array $itemData,
        int $quantity,
        ?int $userId,
    ): void {
        $warehouseProductId = $itemData['warehouse_product_id'] ?? null;

        if ($warehouseProductId === null) {
            return;
        }

        $reservation = $this->resolveReservationForWarehouseProduct($orderItem, (int) $warehouseProductId);

        if ($reservation === null) {
            $this->fail('Unable to resolve warehouse reservation for the damaged item.');
        }

        $stock = InventoryStock::query()
            ->lockForUpdate()
            ->firstOrCreate(
                [
                    'warehouse_id' => $reservation->warehouse_id,
                    'warehouse_product_id' => (int) $warehouseProductId,
                ],
                [
                    'quantity_available' => 0,
                    'quantity_reserved' => 0,
                    'quantity_damaged' => 0,
                ],
            );

        $beforeAvailable = $stock->quantity_available;
        $beforeReserved = $stock->quantity_reserved;
        $beforeDamaged = $stock->quantity_damaged;

        $stock->update([
            'quantity_available' => $beforeAvailable,
            'quantity_reserved' => $beforeReserved,
            'quantity_damaged' => $beforeDamaged + $quantity,
        ]);

        StockMovement::create([
            'warehouse_id' => $reservation->warehouse_id,
            'warehouse_product_id' => (int) $warehouseProductId,
            'type' => StockMovementType::DAMAGED,
            'quantity' => $quantity,
            'before_available' => $beforeAvailable,
            'after_available' => $stock->quantity_available,
            'before_reserved' => $beforeReserved,
            'after_reserved' => $stock->quantity_reserved,
            'before_damaged' => $beforeDamaged,
            'after_damaged' => $stock->quantity_damaged,
            'reference_type' => 'shipment_item_result',
            'reference_id' => $shipmentItemResult->id,
            'notes' => $itemData['notes'] ?? null,
            'created_by' => $userId,
        ]);
    }

    private function resolveReservationForWarehouseProduct(OrderItem $orderItem, int $warehouseProductId): ?OrderStockReservation
    {
        return OrderStockReservation::query()
            ->where('order_item_id', $orderItem->id)
            ->where('warehouse_product_id', $warehouseProductId)
            ->whereIn('status', [
                OrderStockReservationStatus::FULFILLED,
                OrderStockReservationStatus::RESERVED,
                OrderStockReservationStatus::RELEASED,
            ])
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }

    private function resolveOrderItemDeliveryStatus(OrderItem $orderItem, int $cancelledQuantity): string
    {
        $deliveredQuantity = (int) $orderItem->delivered_quantity;
        $returnedQuantity = (int) $orderItem->returned_quantity;
        $damagedQuantity = (int) $orderItem->damaged_quantity;
        $processedQuantity = $deliveredQuantity + $returnedQuantity + $damagedQuantity + $cancelledQuantity;

        if ($processedQuantity === 0) {
            return OrderItemDeliveryStatus::PENDING;
        }

        if ($deliveredQuantity === (int) $orderItem->quantity && $returnedQuantity === 0 && $damagedQuantity === 0 && $cancelledQuantity === 0) {
            return OrderItemDeliveryStatus::DELIVERED;
        }

        if ($returnedQuantity === (int) $orderItem->quantity && $deliveredQuantity === 0 && $damagedQuantity === 0 && $cancelledQuantity === 0) {
            return OrderItemDeliveryStatus::RETURNED;
        }

        if ($damagedQuantity === (int) $orderItem->quantity && $deliveredQuantity === 0 && $returnedQuantity === 0 && $cancelledQuantity === 0) {
            return OrderItemDeliveryStatus::DAMAGED;
        }

        if ($cancelledQuantity === (int) $orderItem->quantity && $deliveredQuantity === 0 && $returnedQuantity === 0 && $damagedQuantity === 0) {
            return OrderItemDeliveryStatus::CANCELLED;
        }

        return OrderItemDeliveryStatus::PARTIALLY_DELIVERED;
    }

    /**
     * @param  array<int, OrderItem>  $orderItems
     * @param  array<int, int|string>  $existingCancelledQuantities
     * @param  array<int, int>  $requestCancelledQuantities
     */
    private function determineFinalShipmentStatus(array $orderItems, array $existingCancelledQuantities, array $requestCancelledQuantities): string
    {
        $allDelivered = true;
        $hasDelivered = false;

        foreach ($orderItems as $orderItem) {
            $cancelledQuantity = (int) ($existingCancelledQuantities[$orderItem->id] ?? 0) + (int) ($requestCancelledQuantities[$orderItem->id] ?? 0);
            $totalProcessed = (int) $orderItem->delivered_quantity + (int) $orderItem->returned_quantity + (int) $orderItem->damaged_quantity + $cancelledQuantity;

            if ((int) $orderItem->delivered_quantity !== (int) $orderItem->quantity || $totalProcessed !== (int) $orderItem->quantity) {
                $allDelivered = false;
            }

            if ((int) $orderItem->delivered_quantity > 0) {
                $hasDelivered = true;
            }
        }

        if ($allDelivered) {
            return ShipmentStatus::DELIVERED;
        }

        if ($hasDelivered) {
            return ShipmentStatus::PARTIALLY_DELIVERED;
        }

        return ShipmentStatus::FAILED;
    }

    private function mapOrderStatus(string $shipmentStatus): string
    {
        return match ($shipmentStatus) {
            ShipmentStatus::DELIVERED => OrderStatus::DELIVERED,
            ShipmentStatus::PARTIALLY_DELIVERED => OrderStatus::PARTIALLY_DELIVERED,
            default => OrderStatus::FAILED,
        };
    }

    private function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
