<?php

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Finance\Enums\PayoutStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMerchantPayoutRequest extends FormRequest
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
            'treasury_account_id' => [
                'sometimes',
                'nullable',
                Rule::exists('treasury_accounts', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'status' => ['sometimes', 'nullable', Rule::in(PayoutStatus::values())],
            'payment_method' => ['sometimes', 'nullable', Rule::in(PaymentMethod::values())],
            'reference_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'rejection_reason' => ['sometimes', 'nullable', 'string'],
            'paid_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
