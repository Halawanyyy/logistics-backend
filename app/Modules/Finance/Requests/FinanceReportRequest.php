<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinanceReportRequest extends FormRequest
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
            'merchant_id' => [
                'nullable',
                Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'driver_id' => [
                'nullable',
                Rule::exists('drivers', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'treasury_account_id' => [
                'nullable',
                Rule::exists('treasury_accounts', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'status' => ['nullable', 'string', 'max:100'],
        ];
    }
}
