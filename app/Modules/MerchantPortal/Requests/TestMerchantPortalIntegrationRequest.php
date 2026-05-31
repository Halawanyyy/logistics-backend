<?php

namespace App\Modules\MerchantPortal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestMerchantPortalIntegrationRequest extends FormRequest
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
            'abilities' => ['nullable', 'array', 'min:1'],
            'abilities.*' => ['string', 'max:100'],
            'merchant_id' => ['prohibited'],
        ];
    }
}
