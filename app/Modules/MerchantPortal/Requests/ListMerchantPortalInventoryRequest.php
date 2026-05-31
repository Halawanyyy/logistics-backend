<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\Warehouse\Requests\ListInventoryStocksRequest;
use Illuminate\Validation\Rule;

class ListMerchantPortalInventoryRequest extends ListInventoryStocksRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'nullable',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'warehouse_product_id' => [
                'nullable',
                Rule::exists('warehouse_products', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'sku' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'low_stock' => ['nullable', 'boolean'],
            'has_reserved' => ['nullable', 'boolean'],
            'available_only' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'merchant_id' => ['prohibited'],
        ];
    }
}
