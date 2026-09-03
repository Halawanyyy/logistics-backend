<?php

namespace App\Modules\Shipment\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);
        $waybill = $this->relationLoaded('waybill') ? $this->waybill : null;

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'shipment_number' => $this->shipment_number,
            'tracking_number' => $this->shipment_number,
            'barcode' => $this->barcode,
            'waybill' => $waybill === null ? null : [
                'id' => $waybill->id,
                'waybill_number' => $waybill->waybill_number,
                'barcode' => $waybill->barcode,
                'status' => $waybill->status,
                'source' => $waybill->source,
            ],
            'merchant_id' => $this->merchant_id,
            'merchant_name' => $this->whenLoaded('merchant', fn () => $this->merchant?->name),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'delivery_governorate_id' => $this->delivery_governorate_id,
            'delivery_governorate_name' => $this->whenLoaded('deliveryGovernorate', fn () => $this->deliveryGovernorate?->name),
            'delivery_area_id' => $this->delivery_area_id,
            'delivery_area_name' => $this->whenLoaded('deliveryArea', fn () => $this->deliveryArea?->name),
            'delivery_address' => $this->delivery_address,
            'cod_amount' => $this->cod_amount,
            'shipping_fee' => $this->shipping_fee,
            'delivered_cod_amount' => $this->delivered_cod_amount,
            'order_type' => $this->whenLoaded('order', fn () => $this->order?->order_type),
            'fulfillment_type' => $this->whenLoaded('order', fn () => $this->order?->fulfillment_type),
            'status' => $this->status,
            'status_label' => $labels->label('shipments.status', $this->status),
            'tracking_notes' => $this->tracking_notes,
            'assigned_driver_id' => $this->assigned_driver_id,
            'assigned_driver_name' => $this->whenLoaded('assignedDriver', fn () => $this->assignedDriver?->name),
            'order_summary' => $this->whenLoaded('order', fn () => [
                'id' => $this->order?->id,
                'order_number' => $this->order?->order_number,
                'status' => $this->order?->status,
                'status_label' => $labels->label('orders.status', $this->order?->status),
                'order_type' => $this->order?->order_type,
                'order_type_label' => $labels->label('orders.type', $this->order?->order_type),
                'fulfillment_type' => $this->order?->fulfillment_type,
                'fulfillment_type_label' => $labels->label('orders.fulfillment_type', $this->order?->fulfillment_type),
                'payment_type' => $this->order?->payment_type,
                'payment_type_label' => $labels->label('orders.payment_type', $this->order?->payment_type),
                'warehouse_fulfillment_status' => $this->order?->warehouse_fulfillment_status,
                'warehouse_fulfillment_status_label' => $labels->label('orders.warehouse_fulfillment_status', $this->order?->warehouse_fulfillment_status),
            ]),
            'merchant_summary' => [
                'id' => $this->merchant_id,
                'name' => $this->relationLoaded('merchant') ? $this->merchant?->name : null,
            ],
            'customer' => [
                'name' => $this->customer_name,
                'phone' => $this->customer_phone,
            ],
            'location_summary' => [
                'delivery_governorate_id' => $this->delivery_governorate_id,
                'delivery_governorate_name' => $this->relationLoaded('deliveryGovernorate') ? $this->deliveryGovernorate?->name : null,
                'delivery_area_id' => $this->delivery_area_id,
                'delivery_area_name' => $this->relationLoaded('deliveryArea') ? $this->deliveryArea?->name : null,
                'delivery_address' => $this->delivery_address,
            ],
            'driver_summary' => [
                'id' => $this->assigned_driver_id,
                'name' => $this->relationLoaded('assignedDriver') ? $this->assignedDriver?->name : null,
            ],
            'cod_summary' => [
                'cod_amount' => $this->cod_amount,
                'delivered_cod_amount' => $this->delivered_cod_amount,
                'shipping_fee' => $this->shipping_fee,
            ],
            'item_results' => $this->whenLoaded('itemResults', fn () => ShipmentItemResultResource::collection($this->itemResults)),
            'histories' => $this->whenLoaded('histories', fn () => ShipmentStatusHistoryResource::collection($this->histories)),
            'allowed_actions' => [
                'can_assign_driver' => ! in_array($this->status, ['delivered', 'partially_delivered', 'failed', 'returned', 'returned_to_hub', 'returned_to_warehouse', 'cancelled'], true),
                'can_update_status' => ! in_array($this->status, ['delivered', 'partially_delivered', 'failed', 'returned', 'returned_to_hub', 'returned_to_warehouse', 'cancelled'], true),
                'can_complete_delivery' => in_array($this->status, ['out_for_delivery', 'delivery_attempted', 'rescheduled'], true),
            ],
            'created_at' => $this->created_at,
        ];
    }
}
