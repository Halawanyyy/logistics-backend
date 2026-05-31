<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\MerchantPortal\Enums\MerchantPortalRole;
use App\Modules\MerchantPortal\Enums\MerchantPortalUserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMerchantPortalUserRequest extends FormRequest
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
        $merchantUser = $this->route('merchantUser');
        $userId = $merchantUser?->user_id;

        return [
            'merchant_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'role' => ['sometimes', 'string', Rule::in(MerchantPortalRole::values())],
            'status' => ['sometimes', 'string', Rule::in(MerchantPortalUserStatus::values())],
            'is_primary' => ['sometimes', 'boolean'],
            'invited_by' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'invited_at' => ['nullable', 'date'],
            'accepted_at' => ['nullable', 'date'],
            'last_login_at' => ['nullable', 'date'],
        ];
    }
}
