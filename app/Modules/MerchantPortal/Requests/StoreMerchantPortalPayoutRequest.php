<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\Finance\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMerchantPortalPayoutRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', Rule::in(PaymentMethod::values())],
            'payment_method' => ['nullable', Rule::in(PaymentMethod::values())],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'merchant_id' => ['prohibited'],
            'status' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'rejected_by' => ['prohibited'],
            'completed_by' => ['prohibited'],
            'approved_at' => ['prohibited'],
            'rejected_at' => ['prohibited'],
            'completed_at' => ['prohibited'],
            'internal_notes' => ['prohibited'],
            'ledger_transaction_id' => ['prohibited'],
            'wallet_transaction_id' => ['prohibited'],
            'treasury_account_id' => ['prohibited'],
            'paid_at' => ['prohibited'],
            'rejection_reason' => ['prohibited'],
        ];
    }
}
