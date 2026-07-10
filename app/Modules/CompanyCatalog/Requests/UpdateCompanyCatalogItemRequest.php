<?php

namespace App\Modules\CompanyCatalog\Requests;

use App\Modules\CompanyCatalog\Models\CompanyCatalogItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyCatalogItemRequest extends FormRequest
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
        $companyCatalogItem = $this->route('companyCatalogItem');
        $companyCatalogItemId = $companyCatalogItem instanceof CompanyCatalogItem ? $companyCatalogItem->id : $companyCatalogItem;

        return [
            'type' => ['sometimes', Rule::in(CompanyCatalogItem::types())],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('company_catalog_items', 'sku')
                    ->ignore($companyCatalogItemId)
                    ->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'size_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'length' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'image_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'is_visible_to_merchants' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in(CompanyCatalogItem::statuses())],
        ];
    }
}
