<?php

namespace App\Modules\Warehouse\Requests;

use App\Modules\Warehouse\Enums\WarehouseReturnCondition;
use App\Modules\Warehouse\Enums\WarehouseReturnStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListWarehouseReturnsRequest extends FormRequest
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
            'condition' => ['nullable', Rule::in(WarehouseReturnCondition::values())],
            'status' => ['nullable', Rule::in(WarehouseReturnStatus::values())],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
