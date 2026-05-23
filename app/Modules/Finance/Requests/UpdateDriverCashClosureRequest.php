<?php

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Enums\DriverCashClosureStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverCashClosureRequest extends FormRequest
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
            'expected_amount' => ['sometimes', 'numeric', 'min:0'],
            'received_amount' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(DriverCashClosureStatus::values())],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
