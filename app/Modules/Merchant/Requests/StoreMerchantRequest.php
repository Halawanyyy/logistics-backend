<?php

namespace App\Modules\Merchant\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMerchantRequest extends FormRequest
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
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'unique:merchants,email'],
            'address' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'pricing_plan_id' => [
                'nullable',
                Rule::exists('pricing_plans', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
        ];
    }
}
