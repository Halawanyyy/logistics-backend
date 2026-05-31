<?php

namespace App\Modules\Label\Requests;

use App\Modules\Label\Enums\LabelFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkWarehouseProductLabelsRequest extends FormRequest
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
            'format' => ['nullable', 'string', Rule::in(LabelFormat::values())],
            'width_mm' => ['nullable', 'numeric', 'min:20', 'max:210'],
            'height_mm' => ['nullable', 'numeric', 'min:20', 'max:297'],
            'download' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.warehouse_product_id' => [
                'required',
                'integer',
                Rule::exists('warehouse_products', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
