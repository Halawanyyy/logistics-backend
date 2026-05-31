<?php

namespace App\Modules\Shipment\Requests;

use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Shipment\Enums\ShipmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShipmentStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(ShipmentStatus::values())],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $shipment = $this->route('shipment');
            $status = (string) $this->input('status');

            if (in_array($status, [
                ShipmentStatus::DELIVERED,
                ShipmentStatus::PARTIALLY_DELIVERED,
                ShipmentStatus::FAILED,
            ], true)) {
                $validator->errors()->add('status', 'Use the complete-delivery endpoint to mark a shipment as delivered, partially delivered, or failed.');
                return;
            }

            if (
                $shipment?->order?->fulfillment_type === OrderFulfillmentType::FROM_WAREHOUSE
                && in_array($status, [
                    ShipmentStatus::RETURNED,
                    ShipmentStatus::RETURNED_TO_HUB,
                    ShipmentStatus::RETURNED_TO_WAREHOUSE,
                ], true)
            ) {
                $validator->errors()->add('status', 'Warehouse-fulfilled returns must be completed through item delivery results so warehouse returns can be created safely.');
            }
        });
    }
}
