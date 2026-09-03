<?php

namespace App\Modules\Waybill\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaybillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'waybill_number' => $this->waybill_number,
            'barcode' => $this->barcode,
            'status' => $this->status,
            'source' => $this->source,
            'batch_id' => $this->batch_id,
            'merchant_id' => $this->merchant_id,
            'order_id' => $this->order_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'shipment_id' => $this->shipment_id,
            'shipment_number' => $this->whenLoaded('shipment', fn () => $this->shipment?->shipment_number),
            'generated_at' => $this->generated_at,
            'assigned_at' => $this->assigned_at,
            'voided_at' => $this->voided_at,
            'void_reason' => $this->void_reason,
        ];
    }
}
