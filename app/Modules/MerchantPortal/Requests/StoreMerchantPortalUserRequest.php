<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\MerchantPortal\Enums\MerchantPortalRole;
use App\Modules\MerchantPortal\Enums\MerchantPortalUserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMerchantPortalUserRequest extends FormRequest
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
            'merchant_id' => ['prohibited'],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
                'required_without_all:name,email,password,password_confirmation',
            ],
            'name' => ['required_without:user_id', 'string', 'max:255'],
            'email' => ['required_without:user_id', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required_without:user_id', 'string', 'min:6', 'confirmed'],
            'role' => ['required', 'string', Rule::in(MerchantPortalRole::values())],
            'status' => ['required', 'string', Rule::in(MerchantPortalUserStatus::values())],
            'is_primary' => ['nullable', 'boolean'],
            'invited_by' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'invited_at' => ['nullable', 'date'],
            'accepted_at' => ['nullable', 'date'],
            'last_login_at' => ['nullable', 'date'],
        ];
    }
}
