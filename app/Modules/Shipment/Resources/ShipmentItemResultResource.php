<?php

namespace App\Modules\Shipment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentItemResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'order_item_id' => $this->order_item_id,
            'product_name' => $this->whenLoaded('orderItem', fn () => $this->orderItem?->product_name),
            'result_status' => $this->result_status,
            'quantity' => $this->quantity,
            'return_condition' => $this->return_condition,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
