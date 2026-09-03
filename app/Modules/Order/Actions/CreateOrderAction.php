<?php

namespace App\Modules\Order\Actions;

use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Enums\OrderSource;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Enums\OrderType;
use App\Modules\Order\Enums\PaymentType;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\OrderPackagingService;
use App\Modules\Order\Services\OrderShippingFeeService;
use App\Modules\Settings\Services\SystemSettingService;
use App\Modules\Warehouse\Services\OrderWarehouseStatusService;
use App\Modules\Waybill\Services\WaybillAssignmentService;
use App\Services\BarcodeService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateOrderAction
{
    public function __construct(
        private readonly BarcodeService $barcodeService,
        private readonly GenerateOrderNumberAction $generateOrderNumberAction,
        private readonly OrderPackagingService $orderPackagingService,
        private readonly OrderShippingFeeService $orderShippingFeeService,
        private readonly SystemSettingService $systemSettingService,
        private readonly OrderWarehouseStatusService $orderWarehouseStatusService,
        private readonly WaybillAssignmentService $waybillAssignmentService,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $overrides
     */
    public function execute(array $validated, ?int $userId, array $overrides = [], bool $reviewOnShippingFailure = false): Order
    {
        return DB::transaction(function () use ($validated, $userId, $overrides, $reviewOnShippingFailure) {
            $orderData = $this->buildOrderAttributes($validated, true);
            $orderData = array_merge($orderData, $overrides);
            $orderData['order_number'] = $this->generateOrderNumberAction->execute();
            $orderData['barcode'] = $this->barcodeService->generateOrderBarcode();
            $orderData['created_by'] = $userId;

            $this->applyShippingFeeOnCreate($validated, $orderData, $reviewOnShippingFailure);

            $order = Order::create($orderData);
            $order->items()->createMany($this->buildItemsPayload($validated['items']));
            $this->orderPackagingService->syncFromPayload($order, $validated, $userId);

            if (($validated['waybill_mode'] ?? 'automatic') === 'preprinted') {
                $this->waybillAssignmentService->assignPreprinted(
                    $order,
                    (string) $validated['preprinted_waybill_number'],
                    $userId,
                );
            }

            return $order->load($this->relations());
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildOrderAttributes(array $validated, bool $applyDefaults = false): array
    {
        $attributes = Arr::only($validated, [
            'merchant_id',
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
            'payment_type',
            'fulfillment_type',
            'is_fragile',
            'allow_inspection',
            'requires_packaging',
            'package_notes',
            'total_packaging_fee',
            'source',
            'external_source',
            'external_order_id',
            'external_order_number',
            'requires_review',
            'review_reason',
            'status',
            'notes',
        ]);

        foreach (['is_fragile', 'allow_inspection', 'requires_packaging', 'requires_review'] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = filter_var($validated[$field], FILTER_VALIDATE_BOOL);
            }
        }

        if ($applyDefaults && ! Arr::exists($attributes, 'order_type')) {
            $attributes['order_type'] = OrderType::DELIVERY;
        }

        if ($applyDefaults && ! Arr::exists($attributes, 'fulfillment_type')) {
            $attributes['fulfillment_type'] = ($attributes['order_type'] ?? OrderType::DELIVERY) === OrderType::RETURN
                ? OrderFulfillmentType::PICKUP_FROM_CUSTOMER
                : OrderFulfillmentType::PICKUP_FROM_MERCHANT;
        }

        $attributes['warehouse_fulfillment_status'] = $this->orderWarehouseStatusService->defaultStatusForFulfillment(
            (string) ($attributes['fulfillment_type'] ?? OrderFulfillmentType::PICKUP_FROM_MERCHANT)
        );

        if ($applyDefaults && ! Arr::exists($attributes, 'source')) {
            $attributes['source'] = OrderSource::MANUAL;
        }

        if ($applyDefaults && ! Arr::exists($attributes, 'status')) {
            $configuredStatus = (string) $this->systemSettingService->get('default_order_status', OrderStatus::DRAFT);
            $attributes['status'] = in_array($configuredStatus, OrderStatus::values(), true)
                ? $configuredStatus
                : OrderStatus::DRAFT;
        }

        if ($applyDefaults && ! Arr::exists($attributes, 'payment_type')) {
            $attributes['payment_type'] = PaymentType::COD;
        }

        if ($applyDefaults && (! Arr::exists($attributes, 'cod_amount') || $attributes['cod_amount'] === null)) {
            $attributes['cod_amount'] = '0.00';
        }

        if (
            $applyDefaults
            && ($attributes['payment_type'] ?? PaymentType::COD) === PaymentType::PREPAID
            && (! Arr::exists($attributes, 'cod_amount') || $attributes['cod_amount'] === null)
        ) {
            $attributes['cod_amount'] = '0.00';
        }

        if (Arr::exists($attributes, 'shipping_fee') && $attributes['shipping_fee'] === null) {
            unset($attributes['shipping_fee']);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $orderData
     */
    private function applyShippingFeeOnCreate(array $validated, array &$orderData, bool $reviewOnShippingFailure): void
    {
        if (Arr::exists($validated, 'shipping_fee') && $validated['shipping_fee'] !== null && $validated['shipping_fee'] !== '') {
            return;
        }

        $resolvedShipping = $this->orderShippingFeeService->resolve(
            (int) $orderData['merchant_id'],
            (int) $orderData['delivery_governorate_id'],
            isset($orderData['delivery_area_id']) ? (int) $orderData['delivery_area_id'] : null,
            (string) ($orderData['order_type'] ?? OrderType::DELIVERY),
        );

        if ($resolvedShipping['source'] === null) {
            if (! $reviewOnShippingFailure) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Shipping rate not configured.',
                ], 422));
            }

            $orderData['shipping_fee'] = '0.00';
            $orderData['requires_review'] = true;
            $orderData['review_reason'] = 'Shipping rate not configured.';
            $orderData['status'] = OrderStatus::PENDING_REVIEW;

            return;
        }

        $orderData['shipping_fee'] = $resolvedShipping['shipping_fee'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildItemsPayload(array $items): array
    {
        return array_map(function (array $item): array {
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
            'originalOrder',
            'shipment',
            'latestStockReservation',
            'items',
            'packagingItems',
            'waybill',
        ];
    }
}
