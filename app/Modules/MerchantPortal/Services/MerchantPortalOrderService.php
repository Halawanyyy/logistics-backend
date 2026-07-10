<?php

namespace App\Modules\MerchantPortal\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Order\Actions\ConfirmOrderAction;
use App\Modules\Order\Actions\CreateOrderAction;
use App\Modules\Order\Enums\OrderSource;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\OrderLifecycleService;
use App\Modules\Order\Services\OrderPackagingService;
use App\Modules\Warehouse\Enums\OrderStockReservationStatus;
use App\Modules\Warehouse\Enums\WarehouseFulfillmentStatus;
use App\Modules\Warehouse\Services\OrderWarehouseStatusService;
use App\Modules\Warehouse\Services\WarehouseOrderReservationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MerchantPortalOrderService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ConfirmOrderAction $confirmOrderAction,
        private readonly CreateOrderAction $createOrderAction,
        private readonly OrderLifecycleService $orderLifecycleService,
        private readonly OrderPackagingService $orderPackagingService,
        private readonly OrderWarehouseStatusService $orderWarehouseStatusService,
        private readonly WarehouseOrderReservationService $warehouseOrderReservationService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function queryForMerchant(int $merchantId, array $filters = []): Builder
    {
        return Order::query()
            ->with($this->relations())
            ->where('merchant_id', $merchantId)
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['fulfillment_type']), fn ($query) => $query->where('fulfillment_type', $filters['fulfillment_type']))
            ->when(isset($filters['payment_type']), fn ($query) => $query->where('payment_type', $filters['payment_type']))
            ->when(isset($filters['order_type']), fn ($query) => $query->where('order_type', $filters['order_type']))
            ->when(isset($filters['customer_phone']), fn ($query) => $query->where('customer_phone', 'like', '%'.$filters['customer_phone'].'%'))
            ->when(isset($filters['order_number']), fn ($query) => $query->where('order_number', 'like', '%'.$filters['order_number'].'%'))
            ->when(isset($filters['warehouse_fulfillment_status']), fn ($query) => $query->where('warehouse_fulfillment_status', $filters['warehouse_fulfillment_status']))
            ->when(isset($filters['delivery_governorate_id']), fn ($query) => $query->where('delivery_governorate_id', $filters['delivery_governorate_id']))
            ->when(isset($filters['delivery_area_id']), fn ($query) => $query->where('delivery_area_id', $filters['delivery_area_id']))
            ->when(($filters['has_shipment'] ?? null) === true, fn ($query) => $query->has('shipment'))
            ->when(($filters['has_shipment'] ?? null) === false, fn ($query) => $query->doesntHave('shipment'))
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('order_number', 'like', '%'.$search.'%')
                        ->orWhere('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('customer_phone', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%');
                });
            })
            ->latest();
    }

    public function findOwned(int $merchantId, int $orderId): Order
    {
        return $this->queryForMerchant($merchantId)
            ->whereKey($orderId)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createForMerchant(array $validated, int $merchantId, ?int $userId): Order
    {
        return $this->createOrderAction->execute(
            $validated,
            $userId,
            [
                'merchant_id' => $merchantId,
                'source' => OrderSource::MANUAL,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Order $order, array $validated): Order
    {
        $this->orderLifecycleService->ensureCanModify($order);

        return DB::transaction(function () use ($order, $validated) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->orderLifecycleService->ensureCanModify($lockedOrder);

            $attributes = $this->buildOrderAttributes($validated);
            $lockedOrder->update($attributes);

            if (Arr::exists($validated, 'items')) {
                $lockedOrder->items()->delete();
                $lockedOrder->items()->createMany($this->buildItemsPayload($validated['items']));
            }

            $this->orderPackagingService->syncFromPayload($lockedOrder, $validated);

            return $lockedOrder->fresh()->load($this->relations());
        });
    }

    public function confirm(Order $order): Order
    {
        return $this->confirmOrderAction->execute($order)->load($this->relations());
    }

    public function cancel(Order $order, ?int $userId): Order
    {
        return DB::transaction(function () use ($order, $userId) {
            $lockedOrder = Order::query()
                ->with('shipment')
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->shipment()->exists()) {
                $this->fail('Order cannot be cancelled after shipment has been created.');
            }

            if (in_array((string) $lockedOrder->status, [
                OrderStatus::SHIPMENT_CREATED,
                OrderStatus::PARTIALLY_DELIVERED,
                OrderStatus::DELIVERED,
                OrderStatus::FAILED,
                OrderStatus::CANCELLED,
            ], true)) {
                $this->fail('Processed orders cannot be cancelled.');
            }

            if ($lockedOrder->fulfillment_type === 'from_warehouse') {
                if (in_array((string) $lockedOrder->warehouse_fulfillment_status, [
                    WarehouseFulfillmentStatus::PICKING,
                    WarehouseFulfillmentStatus::PICKED,
                    WarehouseFulfillmentStatus::PACKING,
                    WarehouseFulfillmentStatus::PACKED,
                    WarehouseFulfillmentStatus::READY_FOR_DISPATCH,
                    WarehouseFulfillmentStatus::FULFILLED,
                    WarehouseFulfillmentStatus::CANCELLED,
                ], true)) {
                    $this->fail('Order cannot be cancelled after warehouse processing has started.');
                }

                if ($lockedOrder->stockReservations()->where('status', OrderStockReservationStatus::FULFILLED)->exists()) {
                    $this->fail('Order cannot be cancelled after warehouse fulfillment has completed.');
                }

                if ($lockedOrder->stockReservations()->where('status', OrderStockReservationStatus::RESERVED)->exists()) {
                    $this->warehouseOrderReservationService->release($lockedOrder);
                    $lockedOrder = $lockedOrder->fresh();
                }
            }

            $oldStatus = (string) $lockedOrder->status;

            $lockedOrder->update([
                'status' => OrderStatus::CANCELLED,
                'warehouse_fulfillment_status' => $lockedOrder->fulfillment_type === 'from_warehouse'
                    ? WarehouseFulfillmentStatus::CANCELLED
                    : $lockedOrder->warehouse_fulfillment_status,
            ]);

            $this->auditLogService->logStatusChange(
                $lockedOrder->fresh(),
                AuditEventType::ORDER_CANCELLED,
                $oldStatus,
                OrderStatus::CANCELLED,
                [
                    'user_id' => $userId,
                ],
                'Order cancelled from merchant portal.',
                null,
                'cancelled',
            );

            return $lockedOrder->fresh()->load($this->relations());
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildOrderAttributes(array $validated): array
    {
        $attributes = Arr::only($validated, [
            'customer_name',
            'customer_phone',
            'customer_phone_alt',
            'delivery_governorate_id',
            'delivery_area_id',
            'delivery_address',
            'delivery_notes',
            'pickup_governorate_id',
            'pickup_area_id',
            'pickup_address',
            'pickup_notes',
            'cod_amount',
            'shipping_fee',
            'order_type',
            'original_order_id',
            'swap_notes',
            'fulfillment_type',
            'is_fragile',
            'allow_inspection',
            'requires_packaging',
            'package_notes',
            'total_packaging_fee',
            'notes',
        ]);

        foreach (['is_fragile', 'allow_inspection', 'requires_packaging'] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = filter_var($validated[$field], FILTER_VALIDATE_BOOL);
            }
        }

        if (Arr::exists($attributes, 'fulfillment_type')) {
            $attributes['warehouse_fulfillment_status'] = $this->orderWarehouseStatusService->defaultStatusForFulfillment(
                (string) $attributes['fulfillment_type']
            );
        }

        if (Arr::exists($attributes, 'shipping_fee') && $attributes['shipping_fee'] === null) {
            unset($attributes['shipping_fee']);
        }

        return $attributes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildItemsPayload(array $items): array
    {
        return array_map(static function (array $item): array {
            return [
                'product_name' => $item['product_name'],
                'sku' => $item['sku'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'] ?? '0.00',
                'weight' => $item['weight'] ?? null,
                'notes' => $item['notes'] ?? null,
            ];
        }, $items);
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'merchant',
            'deliveryGovernorate',
            'deliveryArea',
            'shipment',
            'latestStockReservation',
            'items',
            'packagingItems',
        ];
    }

    private function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
