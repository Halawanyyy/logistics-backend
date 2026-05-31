<?php

namespace App\Modules\Warehouse\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseReturnItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_return_id' => $this->warehouse_return_id,
            'warehouse_product_id' => $this->warehouse_product_id,
            'product_name' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->name),
            'sku' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->sku),
            'barcode' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->barcode),
            'expected_quantity' => $this->expected_quantity,
            'received_quantity' => $this->received_quantity,
            'restock_quantity' => $this->restock_quantity,
            'damaged_quantity' => $this->damaged_quantity,
            'rejected_quantity' => $this->rejected_quantity,
            'condition' => $this->condition,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
