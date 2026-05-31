<?php

namespace App\Modules\Shipment\Requests;

use App\Modules\Order\Enums\OrderType;
use App\Modules\Shipment\Enums\ShipmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListShipmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'assigned_driver_id' => $this->input('assigned_driver_id', $this->input('driver_id')),
            'delivery_governorate_id' => $this->input('delivery_governorate_id', $this->input('governorate_id')),
            'delivery_area_id' => $this->input('delivery_area_id', $this->input('area_id')),
        ], static fn ($value) => $value !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(ShipmentStatus::values())],
            'partially_delivered' => ['nullable', 'boolean'],
            'merchant_id' => [
                'nullable',
                Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'order_type' => ['nullable', Rule::in(OrderType::values())],
            'payment_type' => ['nullable', Rule::in(\App\Modules\Order\Enums\PaymentType::values())],
            'cod_only' => ['nullable', 'boolean'],
            'assigned_driver_id' => [
                'nullable',
                Rule::exists('drivers', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'delivery_governorate_id' => [
                'nullable',
                Rule::exists('governorates', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'delivery_area_id' => [
                'nullable',
                Rule::exists('areas', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'shipment_number' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
