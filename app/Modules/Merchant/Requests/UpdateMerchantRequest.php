<?php

namespace App\Modules\Merchant\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMerchantRequest extends FormRequest
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
        $merchant = $this->route('merchant');
        $merchantId = $merchant?->id ?? $merchant;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', Rule::unique('merchants', 'email')->ignore($merchantId)],
            'address' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'pricing_plan_id' => [
                'nullable',
                Rule::exists('pricing_plans', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
        ];
    }
}
