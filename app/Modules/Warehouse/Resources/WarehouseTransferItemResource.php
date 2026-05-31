<?php

namespace App\Modules\Warehouse\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseTransferItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_transfer_id' => $this->warehouse_transfer_id,
            'warehouse_product_id' => $this->warehouse_product_id,
            'product_name' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->name),
            'sku' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->sku),
            'barcode' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->barcode),
            'quantity' => $this->quantity,
            'received_quantity' => $this->received_quantity,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
