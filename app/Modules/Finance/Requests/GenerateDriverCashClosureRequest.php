<?php

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Enums\DriverCashClosureStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateDriverCashClosureRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'treasury_account_id' => [
                'nullable',
                Rule::exists('treasury_accounts', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'received_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(DriverCashClosureStatus::values())],
            'notes' => ['nullable', 'string'],
        ];
    }
}
