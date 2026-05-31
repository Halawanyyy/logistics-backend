<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\Warehouse\Enums\WarehouseProductStatus;
use App\Modules\Warehouse\Requests\ListWarehouseProductsRequest;
use Illuminate\Validation\Rule;

class ListMerchantPortalWarehouseProductsRequest extends ListWarehouseProductsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(WarehouseProductStatus::values())],
            'low_stock' => ['nullable', 'boolean'],
            'warehouse_id' => [
                'nullable',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'has_stock' => ['nullable', 'boolean'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'merchant_id' => ['prohibited'],
        ];
    }
}
