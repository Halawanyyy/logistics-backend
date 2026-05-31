<?php

namespace App\Modules\Warehouse\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseReceiptItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_receipt_id' => $this->warehouse_receipt_id,
            'warehouse_product_id' => $this->warehouse_product_id,
            'product_name' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->name),
            'sku' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->sku),
            'barcode' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->barcode),
            'quantity' => $this->quantity,
            'accepted_quantity' => $this->accepted_quantity,
            'rejected_quantity' => $this->rejected_quantity,
            'condition' => $this->condition,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
