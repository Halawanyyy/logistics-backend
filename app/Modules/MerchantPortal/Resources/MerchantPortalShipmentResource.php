<?php

namespace App\Modules\MerchantPortal\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);
        $lastAttempt = $this->relationLoaded('deliveryAttempts')
            ? $this->deliveryAttempts->sortByDesc('attempt_number')->first()
            : null;

        return [
            'id' => $this->id,
            'shipment_number' => $this->shipment_number,
            'waybill_number' => $this->relationLoaded('waybill') ? $this->waybill?->waybill_number : null,
            'status' => $this->status,
            'status_label' => $labels->label('shipments.status', $this->status),
            'order_number' => $this->relationLoaded('order') ? $this->order?->order_number : null,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'delivery_location_summary' => [
                'governorate_id' => $this->delivery_governorate_id,
                'governorate_name' => $this->relationLoaded('deliveryGovernorate') ? $this->deliveryGovernorate?->name : null,
                'area_id' => $this->delivery_area_id,
                'area_name' => $this->relationLoaded('deliveryArea') ? $this->deliveryArea?->name : null,
                'address' => $this->delivery_address,
            ],
            'cod_amount' => $this->cod_amount,
            'payment_type' => $this->relationLoaded('order') ? $this->order?->payment_type : null,
            'payment_type_label' => $labels->label('orders.payment_type', $this->relationLoaded('order') ? $this->order?->payment_type : null),
            'assigned_driver_summary' => $this->relationLoaded('assignedDriver') && $this->assignedDriver !== null ? [
                'id' => $this->assignedDriver->id,
                'name' => $this->assignedDriver->name,
            ] : null,
            'last_delivery_attempt_summary' => $lastAttempt !== null ? [
                'attempt_number' => $lastAttempt->attempt_number,
                'attempt_date' => $lastAttempt->attempt_date,
                'result' => $lastAttempt->result,
                'reason' => $lastAttempt->reason,
                'next_action' => $lastAttempt->next_action,
                'next_attempt_date' => $lastAttempt->next_attempt_date,
            ] : null,
            'created_at' => $this->created_at,
        ];
    }
}
