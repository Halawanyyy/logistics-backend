<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\Order\Enums\PaymentType;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Requests\ListShipmentsRequest;
use Illuminate\Validation\Rule;

class ListMerchantPortalShipmentsRequest extends ListShipmentsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in(ShipmentStatus::values())],
            'shipment_number' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'payment_type' => ['nullable', Rule::in(PaymentType::values())],
            'cod_only' => ['nullable', 'boolean'],
            'delivery_governorate_id' => [
                'nullable',
                Rule::exists('governorates', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'delivery_area_id' => [
                'nullable',
                Rule::exists('areas', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'has_driver' => ['nullable', 'boolean'],
            'merchant_id' => ['prohibited'],
            'driver_id' => ['prohibited'],
            'assigned_driver_id' => ['prohibited'],
        ];
    }
}
