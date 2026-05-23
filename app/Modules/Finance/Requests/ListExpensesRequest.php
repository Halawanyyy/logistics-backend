<?php

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Enums\ExpenseCategory;
use App\Modules\Finance\Enums\ExpenseStatus;
use App\Modules\Finance\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListExpensesRequest extends FormRequest
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
            'category' => ['nullable', Rule::in(ExpenseCategory::values())],
            'status' => ['nullable', Rule::in(ExpenseStatus::values())],
            'payment_method' => ['nullable', Rule::in(PaymentMethod::values())],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
