<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\Warehouse\Enums\StockMovementType;
use App\Modules\Warehouse\Requests\ListStockMovementsRequest;
use Illuminate\Validation\Rule;

class ListMerchantPortalStockMovementsRequest extends ListStockMovementsRequest
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
            'movement_type' => ['nullable', Rule::in(StockMovementType::values())],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'integer', 'min:1'],
            'order_id' => [
                'nullable',
                Rule::exists('orders', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sku' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'merchant_id' => ['prohibited'],
        ];
    }
}
