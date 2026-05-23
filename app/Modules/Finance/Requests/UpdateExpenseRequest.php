<?php

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Enums\ExpenseCategory;
use App\Modules\Finance\Enums\ExpenseStatus;
use App\Modules\Finance\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
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
            'category' => ['sometimes', Rule::in(ExpenseCategory::values())],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'status' => ['sometimes', Rule::in(ExpenseStatus::values())],
            'treasury_account_id' => [
                'sometimes',
                'nullable',
                Rule::exists('treasury_accounts', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'expense_date' => ['sometimes', 'nullable', 'date'],
            'payment_method' => ['sometimes', 'nullable', Rule::in(PaymentMethod::values())],
            'reference_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'rejection_reason' => ['sometimes', 'nullable', 'string'],
            'paid_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
