<?php

namespace App\Modules\MerchantPortal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMerchantPortalIntegrationTokenRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'abilities' => ['nullable', 'array', 'min:1'],
            'abilities.*' => ['string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'merchant_id' => ['prohibited'],
        ];
    }
}
