<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\MerchantPortal\Enums\MerchantPortalRole;
use App\Modules\MerchantPortal\Enums\MerchantPortalUserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMerchantPortalUsersRequest extends FormRequest
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
            'role' => ['nullable', 'string', Rule::in(MerchantPortalRole::values())],
            'status' => ['nullable', 'string', Rule::in(MerchantPortalUserStatus::values())],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
