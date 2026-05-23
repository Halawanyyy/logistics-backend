<?php

namespace App\Modules\Warehouse\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PickingListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'picking_number' => $this->picking_number,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->whenLoaded('warehouse', fn () => $this->warehouse?->name),
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,
            'assigned_to_name' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo?->name),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'picked_at' => $this->picked_at,
            'items' => $this->whenLoaded('items', fn () => PickingListItemResource::collection($this->items)),
            'created_at' => $this->created_at,
        ];
    }
}
