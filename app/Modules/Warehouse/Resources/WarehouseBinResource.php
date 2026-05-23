<?php

namespace App\Modules\Warehouse\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseBinResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->whenLoaded('warehouse', fn () => $this->warehouse?->name),
            'warehouse_zone_id' => $this->warehouse_zone_id,
            'zone_name' => $this->whenLoaded('zone', fn () => $this->zone?->name),
            'warehouse_shelf_id' => $this->warehouse_shelf_id,
            'shelf_name' => $this->whenLoaded('shelf', fn () => $this->shelf?->name),
            'name' => $this->name,
            'code' => $this->code,
            'barcode' => $this->barcode,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
