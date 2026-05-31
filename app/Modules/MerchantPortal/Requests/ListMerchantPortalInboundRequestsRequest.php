<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\MerchantPortal\Enums\MerchantInboundStockRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMerchantPortalInboundRequestsRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(MerchantInboundStockRequestStatus::values())],
            'request_number' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'warehouse_id' => [
                'nullable',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'expected_arrival_from' => ['nullable', 'date'],
            'expected_arrival_to' => ['nullable', 'date', 'after_or_equal:expected_arrival_from'],
            'merchant_id' => ['prohibited'],
        ];
    }
}
