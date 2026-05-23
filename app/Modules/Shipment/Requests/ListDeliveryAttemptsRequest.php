<?php

namespace App\Modules\Shipment\Requests;

use App\Modules\Shipment\Enums\DeliveryAttemptReason;
use App\Modules\Shipment\Enums\DeliveryAttemptResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDeliveryAttemptsRequest extends FormRequest
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
            'shipment_id' => ['nullable', Rule::exists('shipments', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'driver_id' => ['nullable', Rule::exists('drivers', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'result' => ['nullable', Rule::in(DeliveryAttemptResult::values())],
            'reason' => ['nullable', Rule::in(DeliveryAttemptReason::values())],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
