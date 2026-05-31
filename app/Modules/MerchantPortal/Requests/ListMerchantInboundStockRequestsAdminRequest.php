<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\MerchantPortal\Enums\MerchantInboundStockRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMerchantInboundStockRequestsAdminRequest extends FormRequest
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
            'merchant_id' => [
                'nullable',
                Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'status' => ['nullable', Rule::in(MerchantInboundStockRequestStatus::values())],
            'warehouse_id' => [
                'nullable',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'request_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
