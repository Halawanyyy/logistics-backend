<?php

namespace App\Modules\Driver\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverManifestItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'shipment_number' => $this->shipment_number,
            'order_id' => $this->whenLoaded('shipment', fn () => $this->shipment?->order_id),
            'order_number' => $this->whenLoaded('shipment', fn () => $this->shipment?->order?->order_number),
            'merchant_id' => $this->whenLoaded('shipment', fn () => $this->shipment?->merchant_id),
            'merchant_name' => $this->whenLoaded('shipment', fn () => $this->shipment?->merchant?->name),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'delivery_address' => $this->delivery_address,
            'cod_amount' => $this->cod_amount,
            'shipping_fee' => $this->shipping_fee,
            'shipment_status_at_loadout' => $this->shipment_status_at_loadout,
            'final_status' => $this->final_status,
            'delivered_cod_amount' => $this->delivered_cod_amount,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
