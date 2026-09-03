<?php

namespace App\Modules\MerchantRegistration\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterMerchantRequest extends FormRequest
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
        $governorateId = $this->input('governorate_id');

        return [
            'company_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', 'unique:merchants,email'],
            'phone' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'address' => ['nullable', 'string', 'max:1000'],
            'governorate_id' => [
                'nullable',
                Rule::exists('governorates', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'area_id' => [
                'nullable',
                Rule::exists('areas', 'id')->where(function ($query) use ($governorateId): void {
                    $query->whereNull('deleted_at');

                    if (filled($governorateId)) {
                        $query->where('governorate_id', $governorateId);
                    }
                }),
            ],
            'status' => ['prohibited'],
            'pricing_plan_id' => ['prohibited'],
            'registration_source' => ['prohibited'],
            'reviewed_by' => ['prohibited'],
            'reviewed_at' => ['prohibited'],
            'rejection_reason' => ['prohibited'],
            'approval_notes' => ['prohibited'],
            'role' => ['prohibited'],
            'permissions' => ['prohibited'],
            'merchant_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'wallet_balance' => ['prohibited'],
            'credit_limit' => ['prohibited'],
            'finance_configuration' => ['prohibited'],
            'internal_settings' => ['prohibited'],
        ];
    }
}
