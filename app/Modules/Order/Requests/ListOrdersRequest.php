<?php

namespace App\Modules\Order\Requests;

use App\Modules\Order\Enums\OrderItemDeliveryStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Enums\OrderType;
use App\Modules\Order\Enums\PaymentType;
use App\Modules\Warehouse\Enums\WarehouseFulfillmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOrdersRequest extends FormRequest
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
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'merchant_id' => [
                'nullable',
                Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'merchant_name' => ['nullable', 'string', 'max:255'],
            'delivery_governorate_id' => [
                'nullable',
                Rule::exists('governorates', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'delivery_area_id' => [
                'nullable',
                Rule::exists('areas', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'status' => ['nullable', Rule::in(OrderStatus::values())],
            'delivery_status' => ['nullable', Rule::in(OrderItemDeliveryStatus::values())],
            'partially_delivered' => ['nullable', 'boolean'],
            'order_type' => ['nullable', Rule::in(OrderType::values())],
            'fulfillment_type' => ['nullable', Rule::in(OrderFulfillmentType::inputValues())],
            'payment_type' => ['nullable', Rule::in(PaymentType::values())],
            'warehouse_fulfillment_status' => ['nullable', Rule::in(WarehouseFulfillmentStatus::values())],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'order_number' => ['nullable', 'string', 'max:255'],
            'has_shipment' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
