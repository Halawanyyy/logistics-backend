<?php

namespace App\Modules\Order\Requests;

use App\Modules\LocationPricing\Models\Area;
use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Enums\OrderSource;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Enums\OrderType;
use App\Modules\Order\Enums\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'merchant_id' => [
                'required',
                Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_phone_alt' => ['nullable', 'string', 'max:50'],

            'delivery_governorate_id' => [
                'required',
                Rule::exists('governorates', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'delivery_area_id' => [
                'nullable',
                Rule::exists('areas', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'delivery_address' => ['required', 'string'],
            'delivery_notes' => ['nullable', 'string'],

            'pickup_governorate_id' => [
                'nullable',
                Rule::exists('governorates', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'pickup_area_id' => [
                'nullable',
                Rule::exists('areas', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'pickup_address' => ['nullable', 'string'],
            'pickup_notes' => ['nullable', 'string'],

            'cod_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'order_type' => ['nullable', Rule::in(OrderType::values())],
            'original_order_id' => [
                'nullable',
                Rule::exists('orders', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'swap_notes' => ['nullable', 'string'],
            'payment_type' => ['nullable', Rule::in(PaymentType::values())],

            'fulfillment_type' => ['nullable', Rule::in(OrderFulfillmentType::values())],
            'is_fragile' => ['nullable', 'boolean'],
            'allow_inspection' => ['nullable', 'boolean'],
            'requires_packaging' => ['nullable', 'boolean'],
            'package_notes' => ['nullable', 'string'],

            'source' => ['nullable', Rule::in(OrderSource::values())],
            'external_source' => ['nullable', 'string', 'max:100'],
            'external_order_id' => ['nullable', 'string', 'max:255'],
            'external_order_number' => ['nullable', 'string', 'max:255'],
            'requires_review' => ['nullable', 'boolean'],
            'review_reason' => ['nullable', 'string'],

            'status' => ['nullable', Rule::in(OrderStatus::values())],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
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

            $deliveryAreaId = $this->input('delivery_area_id');

            if ($deliveryAreaId !== null) {
                $belongsToGovernorate = Area::query()
                    ->whereKey($deliveryAreaId)
                    ->where('governorate_id', $this->input('delivery_governorate_id'))
                    ->exists();

                if (! $belongsToGovernorate) {
                    $validator->errors()->add('delivery_area_id', 'The selected delivery area does not belong to the selected delivery governorate.');
                }
            }

            $pickupAreaId = $this->input('pickup_area_id');

            if ($pickupAreaId === null) {
                return;
            }

            $pickupGovernorateId = $this->input('pickup_governorate_id');

            if ($pickupGovernorateId === null) {
                $validator->errors()->add('pickup_governorate_id', 'The pickup governorate field is required when pickup area is present.');
                return;
            }

            $pickupAreaBelongsToGovernorate = Area::query()
                ->whereKey($pickupAreaId)
                ->where('governorate_id', $pickupGovernorateId)
                ->exists();

            if (! $pickupAreaBelongsToGovernorate) {
                $validator->errors()->add('pickup_area_id', 'The selected pickup area does not belong to the selected pickup governorate.');
            }

            $orderType = $this->input('order_type', OrderType::DELIVERY);
            $fulfillmentType = $this->input('fulfillment_type');
            $originalOrderId = $this->input('original_order_id');

            if ($orderType === OrderType::RETURN) {
                if ($fulfillmentType !== null && $fulfillmentType !== OrderFulfillmentType::PICKUP_FROM_CUSTOMER) {
                    $validator->errors()->add('fulfillment_type', 'Return orders must use pickup_from_customer fulfillment type.');
                }
            } elseif ($fulfillmentType === OrderFulfillmentType::PICKUP_FROM_CUSTOMER) {
                $validator->errors()->add('fulfillment_type', 'The pickup_from_customer fulfillment type is only allowed for return orders.');
            }

            if ($originalOrderId !== null && $orderType !== OrderType::SWAP) {
                $validator->errors()->add('original_order_id', 'The original order id field is only allowed for swap orders.');
            }
        });
    }
}
