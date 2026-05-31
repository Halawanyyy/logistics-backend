<?php

namespace App\Modules\Warehouse\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListInventoryStocksRequest extends FormRequest
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
            'warehouse_id' => [
                'nullable',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'merchant_id' => [
                'nullable',
                Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'warehouse_product_id' => [
                'nullable',
                Rule::exists('warehouse_products', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'warehouse_bin_id' => [
                'nullable',
                Rule::exists('warehouse_bins', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'sku' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'low_stock' => ['nullable', 'boolean'],
            'has_reserved' => ['nullable', 'boolean'],
            'available_only' => ['nullable', 'boolean'],
        ];
    }
}
