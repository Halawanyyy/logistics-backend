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

            'fulfillment_type' => ['nullable', Rule::in(OrderFulfillmentType::inputValues())],
            'is_fragile' => ['nullable', 'boolean'],
            'allow_inspection' => ['nullable', 'boolean'],
            'requires_packaging' => ['nullable', 'boolean'],
            'package_notes' => ['nullable', 'string'],
            'packaging_items' => ['nullable', 'array'],
            'packaging_items.*.company_catalog_item_id' => [
                'required_with:packaging_items',
                Rule::exists('company_catalog_items', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where('status', 'active')
                    ->where('type', 'packaging_product')),
            ],
            'packaging_items.*.quantity' => ['required_with:packaging_items', 'integer', 'min:1'],
            'packaging_items.*.notes' => ['nullable', 'string'],

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
                    $validator->errors()->add('delivery_area_id', __('validation.custom.delivery_area_id.belongs_to_governorate'));
                }
            }

            $pickupAreaId = $this->input('pickup_area_id');

            if ($pickupAreaId === null) {
                return;
            }

            $pickupGovernorateId = $this->input('pickup_governorate_id');

            if ($pickupGovernorateId === null) {
                $validator->errors()->add('pickup_governorate_id', __('validation.custom.pickup_governorate_id.required_with_pickup_area'));

                return;
            }

            $pickupAreaBelongsToGovernorate = Area::query()
                ->whereKey($pickupAreaId)
                ->where('governorate_id', $pickupGovernorateId)
                ->exists();

            if (! $pickupAreaBelongsToGovernorate) {
                $validator->errors()->add('pickup_area_id', __('validation.custom.pickup_area_id.belongs_to_governorate'));
            }

            $orderType = $this->input('order_type', OrderType::DELIVERY);
            $fulfillmentType = $this->input('fulfillment_type');
            $originalOrderId = $this->input('original_order_id');

            if ($orderType === OrderType::RETURN) {
                if ($fulfillmentType !== null && $fulfillmentType !== OrderFulfillmentType::PICKUP_FROM_CUSTOMER) {
                    $validator->errors()->add('fulfillment_type', __('validation.custom.fulfillment_type.return_orders_only'));
                }
            } elseif ($fulfillmentType === OrderFulfillmentType::PICKUP_FROM_CUSTOMER) {
                $validator->errors()->add('fulfillment_type', __('validation.custom.fulfillment_type.pickup_from_customer_only_for_returns'));
            }

            if ($originalOrderId !== null && $orderType !== OrderType::SWAP) {
                $validator->errors()->add('original_order_id', __('validation.custom.original_order_id.only_for_swap_orders'));
            }
        });
    }
}
