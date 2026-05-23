<?php

namespace App\Modules\Shipment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'shipment_number' => $this->shipment_number,
            'barcode' => $this->barcode,
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
            'tracking_notes' => $this->tracking_notes,
            'assigned_driver_id' => $this->assigned_driver_id,
            'assigned_driver_name' => $this->whenLoaded('assignedDriver', fn () => $this->assignedDriver?->name),
            'item_results' => $this->whenLoaded('itemResults', fn () => ShipmentItemResultResource::collection($this->itemResults)),
            'histories' => $this->whenLoaded('histories', fn () => ShipmentStatusHistoryResource::collection($this->histories)),
            'created_at' => $this->created_at,
        ];
    }
}
