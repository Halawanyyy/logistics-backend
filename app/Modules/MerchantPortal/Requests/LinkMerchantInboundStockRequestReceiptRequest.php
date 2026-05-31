<?php

namespace App\Modules\MerchantPortal\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkMerchantInboundStockRequestReceiptRequest extends FormRequest
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
            'warehouse_receipt_id' => [
                'required',
                Rule::exists('warehouse_receipts', 'id'),
            ],
            'internal_notes' => ['nullable', 'string'],
        ];
    }
}
