<?php

namespace App\Modules\Warehouse\Requests;

use App\Modules\Warehouse\Enums\WarehouseReturnCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InspectWarehouseReturnRequest extends FormRequest
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
            'items.*.condition' => ['required', Rule::in(WarehouseReturnCondition::values())],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
