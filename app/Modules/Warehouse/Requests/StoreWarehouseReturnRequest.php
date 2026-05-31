<?php

namespace App\Modules\Warehouse\Requests;

use App\Modules\Warehouse\Enums\WarehouseReturnCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseReturnRequest extends FormRequest
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
            'shipment_id' => [
                'nullable',
                Rule::exists('shipments', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'order_id' => [
                'nullable',
                Rule::exists('orders', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'warehouse_product_id' => [
                'nullable',
                Rule::exists('warehouse_products', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'condition' => ['nullable', Rule::in(WarehouseReturnCondition::values())],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.warehouse_product_id' => [
                'required_with:items',
                Rule::exists('warehouse_products', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'items.*.expected_quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.condition' => ['nullable', Rule::in(WarehouseReturnCondition::values())],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $hasLegacyFields = $this->filled('warehouse_product_id') && $this->filled('quantity');
            $hasItems = is_array($this->input('items')) && count($this->input('items')) > 0;

            if (! $hasLegacyFields && ! $hasItems) {
                $validator->errors()->add('items', 'Either warehouse_product_id and quantity or an items array is required.');
            }

            if ($hasLegacyFields && ! $this->filled('condition')) {
                $validator->errors()->add('condition', 'The condition field is required for legacy single-item returns.');
            }
        });
    }
}
