<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\Warehouse\Enums\WarehouseChargeStatus;
use App\Modules\Warehouse\Enums\WarehouseChargeType;
use App\Modules\Warehouse\Requests\ListWarehouseChargesRequest;
use Illuminate\Validation\Rule;

class ListMerchantPortalWarehouseChargesRequest extends ListWarehouseChargesRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'charge_type' => ['nullable', Rule::in(WarehouseChargeType::values())],
            'status' => ['nullable', Rule::in(WarehouseChargeStatus::values())],
            'warehouse_id' => [
                'nullable',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'search' => ['nullable', 'string', 'max:255'],
            'merchant_id' => ['prohibited'],
        ];
    }
}
