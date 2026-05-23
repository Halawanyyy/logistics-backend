<?php

namespace App\Modules\Warehouse\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackingTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'packing_number' => $this->packing_number,
            'picking_list_id' => $this->picking_list_id,
            'picking_number_ref' => $this->whenLoaded('pickingList', fn () => $this->pickingList?->picking_number),
            'order_id' => $this->order_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'shipment_id' => $this->shipment_id,
            'shipment_number' => $this->whenLoaded('shipment', fn () => $this->shipment?->shipment_number),
            'status' => $this->status,
            'package_weight' => $this->package_weight,
            'package_length' => $this->package_length,
            'package_width' => $this->package_width,
            'package_height' => $this->package_height,
            'packaging_fee' => $this->packaging_fee,
            'notes' => $this->notes,
            'packed_by' => $this->packed_by,
            'packed_by_name' => $this->whenLoaded('packedBy', fn () => $this->packedBy?->name),
            'packed_at' => $this->packed_at,
            'created_at' => $this->created_at,
        ];
    }
}
