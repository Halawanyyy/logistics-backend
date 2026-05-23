<?php

namespace App\Modules\Shipment\Requests;

use App\Modules\Shipment\Enums\DeliveryAttemptReason;
use App\Modules\Shipment\Enums\DeliveryAttemptResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryAttemptRequest extends FormRequest
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
            'result' => ['required', Rule::in(DeliveryAttemptResult::values())],
            'reason' => ['nullable', Rule::in(DeliveryAttemptReason::values())],
            'notes' => ['nullable', 'string'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'next_attempt_date' => ['nullable', 'date'],
        ];
    }
}
