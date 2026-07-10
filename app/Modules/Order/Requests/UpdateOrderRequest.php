<?php

namespace App\Modules\Order\Requests;

use App\Modules\LocationPricing\Models\Area;
use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Enums\OrderSource;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Enums\OrderType;
use App\Modules\Order\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('fulfillment_type')) {
            $this->merge([
                'fulfillment_type' => OrderFulfillmentType::normalize($this->input('fulfillment_type')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'merchant_id' => [
                'sometimes',
                Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'customer_name' => ['sometimes', 'string', 'max:255'],
            'customer_phone' => ['sometimes', 'string', 'max:50'],
            'customer_phone_alt' => ['sometimes', 'nullable', 'string', 'max:50'],

            'delivery_governorate_id' => [
                'sometimes',
                Rule::exists('governorates', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'delivery_area_id' => [
                'sometimes',
                'nullable',
                Rule::exists('areas', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'delivery_address' => ['sometimes', 'string'],
            'delivery_notes' => ['sometimes', 'nullable', 'string'],

            'pickup_governorate_id' => [
                'sometimes',
                'nullable',
                Rule::exists('governorates', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'pickup_area_id' => [
                'sometimes',
                'nullable',
                Rule::exists('areas', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'pickup_address' => ['sometimes', 'nullable', 'string'],
            'pickup_notes' => ['sometimes', 'nullable', 'string'],

            'cod_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'shipping_fee' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'order_type' => ['sometimes', Rule::in(OrderType::values())],
            'original_order_id' => [
                'sometimes',
                'nullable',
                Rule::exists('orders', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'swap_notes' => ['sometimes', 'nullable', 'string'],

            'fulfillment_type' => ['sometimes', Rule::in(OrderFulfillmentType::inputValues())],
            'is_fragile' => ['sometimes', 'boolean'],
            'allow_inspection' => ['sometimes', 'boolean'],
            'requires_packaging' => ['sometimes', 'boolean'],
            'package_notes' => ['sometimes', 'nullable', 'string'],
            'packaging_items' => ['sometimes', 'array'],
            'packaging_items.*.company_catalog_item_id' => [
                'required_with:packaging_items',
                Rule::exists('company_catalog_items', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where('status', 'active')
                    ->where('type', 'packaging_product')),
            ],
            'packaging_items.*.quantity' => ['required_with:packaging_items', 'integer', 'min:1'],
            'packaging_items.*.notes' => ['nullable', 'string'],

            'source' => ['sometimes', Rule::in(OrderSource::values())],
            'external_source' => ['sometimes', 'nullable', 'string', 'max:100'],
            'external_order_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'external_order_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'requires_review' => ['sometimes', 'boolean'],
            'review_reason' => ['sometimes', 'nullable', 'string'],

            'status' => ['sometimes', Rule::in(OrderStatus::values())],
            'notes' => ['sometimes', 'nullable', 'string'],

            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $order = $this->resolveRouteOrder();

            if (! $order instanceof Order) {
                return;
            }

            $finalDeliveryGovernorateId = $this->input('delivery_governorate_id', $order->delivery_governorate_id);
            $finalDeliveryAreaId = $this->has('delivery_area_id') ? $this->input('delivery_area_id') : $order->delivery_area_id;

            if ($finalDeliveryAreaId !== null) {
                $deliveryAreaBelongsToGovernorate = Area::query()
                    ->whereKey($finalDeliveryAreaId)
                    ->where('governorate_id', $finalDeliveryGovernorateId)
                    ->exists();

                if (! $deliveryAreaBelongsToGovernorate) {
                    $validator->errors()->add('delivery_area_id', 'The selected delivery area does not belong to the selected delivery governorate.');
                }
            }

            $finalPickupGovernorateId = $this->has('pickup_governorate_id') ? $this->input('pickup_governorate_id') : $order->pickup_governorate_id;
            $finalPickupAreaId = $this->has('pickup_area_id') ? $this->input('pickup_area_id') : $order->pickup_area_id;

            if ($finalPickupAreaId === null) {
                return;
            }

            if ($finalPickupGovernorateId === null) {
                $validator->errors()->add('pickup_governorate_id', 'The pickup governorate field is required when pickup area is present.');

                return;
            }

            $pickupAreaBelongsToGovernorate = Area::query()
                ->whereKey($finalPickupAreaId)
                ->where('governorate_id', $finalPickupGovernorateId)
                ->exists();

            if (! $pickupAreaBelongsToGovernorate) {
                $validator->errors()->add('pickup_area_id', 'The selected pickup area does not belong to the selected pickup governorate.');
            }

            $finalOrderType = $this->input('order_type', $order->order_type);
            $finalFulfillmentType = $this->has('fulfillment_type') ? $this->input('fulfillment_type') : $order->fulfillment_type;
            $finalOriginalOrderId = $this->has('original_order_id') ? $this->input('original_order_id') : $order->original_order_id;

            if ($finalOrderType === OrderType::RETURN && $finalFulfillmentType !== OrderFulfillmentType::PICKUP_FROM_CUSTOMER) {
                $validator->errors()->add('fulfillment_type', 'Return orders must use pickup_from_customer fulfillment type.');
            }

            if ($finalOrderType !== OrderType::RETURN && $finalFulfillmentType === OrderFulfillmentType::PICKUP_FROM_CUSTOMER) {
                $validator->errors()->add('fulfillment_type', 'The pickup_from_customer fulfillment type is only allowed for return orders.');
            }

            if ($finalOriginalOrderId !== null && $finalOrderType !== OrderType::SWAP) {
                $validator->errors()->add('original_order_id', 'The original order id field is only allowed for swap orders.');
            }

            if ($finalOriginalOrderId !== null && (int) $finalOriginalOrderId === (int) $order->id) {
                $validator->errors()->add('original_order_id', 'The original order id field cannot reference the same order.');
            }

            if (
                $this->has('fulfillment_type')
                && $finalFulfillmentType !== $order->fulfillment_type
                && (
                    $order->shipment()->exists()
                    || $order->stockReservations()->exists()
                    || ! in_array($order->warehouse_fulfillment_status, [null, 'not_required', 'pending_reservation'], true)
                )
            ) {
                $validator->errors()->add('fulfillment_type', 'The fulfillment type cannot be changed after warehouse or shipment processing has started.');
            }
        });
    }

    protected function resolveRouteOrder(): ?Order
    {
        $routeOrder = $this->route('order');

        if ($routeOrder instanceof Order) {
            return $routeOrder;
        }

        if (is_numeric($routeOrder)) {
            return Order::query()->find((int) $routeOrder);
        }

        return null;
    }
}
