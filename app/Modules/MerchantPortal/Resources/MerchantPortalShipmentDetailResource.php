<?php

namespace App\Modules\MerchantPortal\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalShipmentDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);

        return [
            'id' => $this->id,
            'shipment_number' => $this->shipment_number,
            'tracking_number' => $this->shipment_number,
            'barcode' => $this->barcode,
            'waybill' => $this->relationLoaded('waybill') && $this->waybill !== null ? [
                'id' => $this->waybill->id,
                'waybill_number' => $this->waybill->waybill_number,
                'barcode' => $this->waybill->barcode,
                'source' => $this->waybill->source,
            ] : null,
            'status' => $this->status,
            'status_label' => $labels->label('shipments.status', $this->status),
            'order_summary' => $this->relationLoaded('order') ? [
                'id' => $this->order?->id,
                'order_number' => $this->order?->order_number,
                'status' => $this->order?->status,
                'status_label' => $labels->label('orders.status', $this->order?->status),
                'order_type' => $this->order?->order_type,
                'order_type_label' => $labels->label('orders.type', $this->order?->order_type),
                'fulfillment_type' => $this->order?->fulfillment_type,
                'fulfillment_type_label' => $labels->label('orders.fulfillment_type', $this->order?->fulfillment_type),
                'warehouse_fulfillment_status' => $this->order?->warehouse_fulfillment_status,
                'warehouse_fulfillment_status_label' => $labels->label('orders.warehouse_fulfillment_status', $this->order?->warehouse_fulfillment_status),
            ] : null,
            'customer' => [
                'name' => $this->customer_name,
                'phone' => $this->customer_phone,
            ],
            'delivery' => [
                'governorate_id' => $this->delivery_governorate_id,
                'governorate_name' => $this->relationLoaded('deliveryGovernorate') ? $this->deliveryGovernorate?->name : null,
                'area_id' => $this->delivery_area_id,
                'area_name' => $this->relationLoaded('deliveryArea') ? $this->deliveryArea?->name : null,
                'address' => $this->delivery_address,
            ],
            'driver_summary' => $this->relationLoaded('assignedDriver') && $this->assignedDriver !== null ? [
                'id' => $this->assignedDriver->id,
                'name' => $this->assignedDriver->name,
            ] : null,
            'payment_summary' => [
                'payment_type' => $this->relationLoaded('order') ? $this->order?->payment_type : null,
                'payment_type_label' => $labels->label('orders.payment_type', $this->relationLoaded('order') ? $this->order?->payment_type : null),
                'cod_amount' => $this->cod_amount,
                'shipping_fee' => $this->shipping_fee,
                'delivered_cod_amount' => $this->delivered_cod_amount,
            ],
            'tracking_events' => $this->whenLoaded('histories', function () use ($labels) {
                return $this->histories->map(fn ($history) => [
                    'status' => $history->status,
                    'status_label' => $labels->label('shipments.status', $history->status),
                    'notes' => $history->notes,
                    'created_at' => $history->created_at,
                ])->values();
            }),
            'delivery_attempts' => $this->whenLoaded('deliveryAttempts', function () {
                return $this->deliveryAttempts->map(fn ($attempt) => [
                    'attempt_number' => $attempt->attempt_number,
                    'attempt_date' => $attempt->attempt_date,
                    'result' => $attempt->result,
                    'reason' => $attempt->reason,
                    'notes' => $attempt->notes,
                    'next_action' => $attempt->next_action,
                    'next_attempt_date' => $attempt->next_attempt_date,
                ])->values();
            }),
            'label_available' => true,
            'waybill_available' => true,
            'created_at' => $this->created_at,
        ];
    }
}
