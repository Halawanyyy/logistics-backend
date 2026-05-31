<?php

namespace App\Modules\Warehouse\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveWarehouseReturnRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.warehouse_return_item_id' => [
                'required',
                Rule::exists('warehouse_return_items', 'id'),
            ],
            'items.*.received_quantity' => ['required', 'integer', 'min:0'],
        ];
    }
}
