<?php

namespace App\Modules\CompanyCatalog\Requests;

use App\Modules\CompanyCatalog\Models\CompanyCatalogItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyCatalogItemRequest extends FormRequest
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
            'type' => ['required', Rule::in(CompanyCatalogItem::types())],
            'category' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('company_catalog_items', 'sku')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'description' => ['nullable', 'string'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'size_label' => ['nullable', 'string', 'max:255'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'image_path' => ['nullable', 'string', 'max:2048'],
            'is_visible_to_merchants' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(CompanyCatalogItem::statuses())],
        ];
    }
}
