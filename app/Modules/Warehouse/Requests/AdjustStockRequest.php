<?php

namespace App\Modules\Warehouse\Requests;

use App\Modules\Warehouse\Enums\StockMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
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
                'required',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'warehouse_product_id' => [
                'required',
                Rule::exists('warehouse_products', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'warehouse_bin_id' => [
                'nullable',
                Rule::exists('warehouse_bins', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'type' => ['required', Rule::in(StockMovementType::values())],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
