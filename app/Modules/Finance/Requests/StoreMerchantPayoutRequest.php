<?php

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Finance\Enums\PayoutStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMerchantPayoutRequest extends FormRequest
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
            'merchant_id' => [
                'required',
                Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'treasury_account_id' => [
                'nullable',
                Rule::exists('treasury_accounts', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'status' => ['nullable', Rule::in(PayoutStatus::values())],
            'payment_method' => ['nullable', Rule::in(PaymentMethod::values())],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'string'],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
