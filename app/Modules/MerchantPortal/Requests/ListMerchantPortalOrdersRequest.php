<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\LocationPricing\Models\Area;
use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Enums\OrderType;
use App\Modules\Order\Enums\PaymentType;
use App\Modules\Order\Requests\ListOrdersRequest;
use App\Modules\Warehouse\Enums\WarehouseFulfillmentStatus;
use Illuminate\Validation\Rule;

class ListMerchantPortalOrdersRequest extends ListOrdersRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in(OrderStatus::values())],
            'fulfillment_type' => ['nullable', Rule::in(OrderFulfillmentType::inputValues())],
            'payment_type' => ['nullable', Rule::in(PaymentType::values())],
            'order_type' => ['nullable', Rule::in(OrderType::values())],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'order_number' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'has_shipment' => ['nullable', 'boolean'],
            'warehouse_fulfillment_status' => ['nullable', Rule::in(WarehouseFulfillmentStatus::values())],
            'delivery_governorate_id' => [
                'nullable',
                Rule::exists('governorates', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'delivery_area_id' => [
                'nullable',
                Rule::exists('areas', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'merchant_id' => ['prohibited'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $deliveryAreaId = $this->input('delivery_area_id');

            if ($deliveryAreaId === null || $this->input('delivery_governorate_id') === null) {
                return;
            }

            $belongsToGovernorate = Area::query()
                ->whereKey($deliveryAreaId)
                ->where('governorate_id', $this->input('delivery_governorate_id'))
                ->exists();

            if (! $belongsToGovernorate) {
                $validator->errors()->add('delivery_area_id', 'The selected delivery area does not belong to the selected delivery governorate.');
            }
        });
    }
}
