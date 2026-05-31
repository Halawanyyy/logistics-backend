<?php

namespace App\Modules\MerchantPortal\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMerchantPortalAttachmentRequest extends FormRequest
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
            'context' => ['required', 'string', Rule::in([
                'merchant_profile',
                'order',
                'shipment',
                'invoice',
                'payout',
                'inbound_stock_request',
                'integration',
                'support_document',
            ])],
            'resource_id' => [
                Rule::requiredIf(fn (): bool => ! in_array($this->input('context'), ['merchant_profile', 'support_document'], true)),
                'nullable',
                'integer',
                'min:1',
            ],
            'notes' => ['nullable', 'string'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,csv,xlsx'],
            'merchant_id' => ['prohibited'],
        ];
    }
}
