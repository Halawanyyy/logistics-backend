<?php

namespace App\Modules\CompanyCatalog\Requests;

use App\Modules\CompanyCatalog\Models\CompanyCatalogItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCompanyCatalogItemsRequest extends FormRequest
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
            'type' => ['nullable', Rule::in(CompanyCatalogItem::types())],
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(CompanyCatalogItem::statuses())],
            'is_visible_to_merchants' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
