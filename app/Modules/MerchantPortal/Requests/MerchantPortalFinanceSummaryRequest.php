<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\Finance\Requests\MerchantFinanceSummaryRequest;

class MerchantPortalFinanceSummaryRequest extends MerchantFinanceSummaryRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'delivery_governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'merchant_id' => ['prohibited'],
        ];
    }
}
