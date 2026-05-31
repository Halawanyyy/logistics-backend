<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\Label\Enums\LabelFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkMerchantPortalWarehouseProductLabelsRequest extends FormRequest
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
            'merchant_id' => ['prohibited'],
            'format' => ['nullable', Rule::in(LabelFormat::values())],
            'width_mm' => ['nullable', 'numeric', 'min:20', 'max:210'],
            'height_mm' => ['nullable', 'numeric', 'min:20', 'max:297'],
            'download' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.warehouse_product_id' => [
                'required',
                Rule::exists('warehouse_products', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $totalQuantity = collect($this->input('items', []))
                ->sum(fn (array $item): int => (int) ($item['quantity'] ?? 0));

            if ($totalQuantity > 200) {
                $validator->errors()->add('items', 'The total bulk label quantity cannot exceed 200.');
            }
        });
    }
}
