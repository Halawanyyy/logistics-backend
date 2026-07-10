<?php

namespace App\Modules\MerchantPortal\Requests;

use Illuminate\Validation\Rule;

class ListMerchantPortalAreasRequest extends MerchantPortalScopedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'governorate_id' => [
                'required',
                'integer',
                Rule::exists('governorates', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where('is_active', true)),
            ],
        ]);
    }
}
