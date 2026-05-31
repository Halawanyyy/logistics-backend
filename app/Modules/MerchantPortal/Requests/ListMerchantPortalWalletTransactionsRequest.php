<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\Finance\Enums\MerchantWalletDirection;
use App\Modules\Finance\Enums\MerchantWalletTransactionStatus;
use App\Modules\Finance\Enums\MerchantWalletTransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMerchantPortalWalletTransactionsRequest extends FormRequest
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
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'type' => ['nullable', Rule::in(MerchantWalletTransactionType::values())],
            'direction' => ['nullable', Rule::in(MerchantWalletDirection::values())],
            'status' => ['nullable', Rule::in(MerchantWalletTransactionStatus::values())],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'merchant_id' => ['prohibited'],
        ];
    }
}
