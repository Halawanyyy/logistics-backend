<?php

namespace App\Modules\Shipment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'shipment_number' => $this->whenLoaded('shipment', fn () => $this->shipment?->shipment_number),
            'shipment_status' => $this->whenLoaded('shipment', fn () => $this->shipment?->status),
            'driver_id' => $this->driver_id,
            'driver_name' => $this->whenLoaded('driver', fn () => $this->driver?->name),
            'attempt_number' => $this->attempt_number,
            'attempt_date' => $this->attempt_date,
            'result' => $this->result,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'next_action' => $this->next_action,
            'next_attempt_date' => $this->next_attempt_date,
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'created_at' => $this->created_at,
        ];
    }
}
