<?php

namespace App\Modules\MerchantPortal\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMerchantPortalProfileRequest extends FormRequest
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
        $merchantId = $this->attributes->get('merchant_id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('merchants', 'email')->ignore($merchantId)],
            'address' => ['sometimes', 'nullable', 'string'],
            'id' => ['prohibited'],
            'merchant_id' => ['prohibited'],
            'status' => ['prohibited'],
            'pricing_plan_id' => ['prohibited'],
            'wallet_balance' => ['prohibited'],
            'wallet_balances' => ['prohibited'],
            'financial_fields' => ['prohibited'],
            'financial_settings' => ['prohibited'],
            'commission_settings' => ['prohibited'],
            'commission' => ['prohibited'],
            'internal_notes' => ['prohibited'],
            'deleted_at' => ['prohibited'],
            'created_by' => ['prohibited'],
            'updated_by' => ['prohibited'],
        ];
    }
}
