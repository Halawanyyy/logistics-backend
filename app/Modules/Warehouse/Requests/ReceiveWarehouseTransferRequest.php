<?php

namespace App\Modules\Warehouse\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveWarehouseTransferRequest extends FormRequest
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
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.warehouse_transfer_item_id' => [
                'required_with:items',
                Rule::exists('warehouse_transfer_items', 'id'),
            ],
            'items.*.received_quantity' => ['required_with:items', 'integer', 'min:0'],
        ];
    }
}
