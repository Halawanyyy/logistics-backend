<?php

namespace App\Modules\Warehouse\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryStockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->whenLoaded('warehouse', fn () => $this->warehouse?->name),
            'warehouse_product_id' => $this->warehouse_product_id,
            'product_name' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->name),
            'merchant_id' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->merchant_id),
            'merchant_name' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->merchant?->name),
            'warehouse_bin_id' => $this->warehouse_bin_id,
            'bin_name' => $this->whenLoaded('warehouseBin', fn () => $this->warehouseBin?->name),
            'bin_code' => $this->whenLoaded('warehouseBin', fn () => $this->warehouseBin?->code),
            'bin_barcode' => $this->whenLoaded('warehouseBin', fn () => $this->warehouseBin?->barcode),
            'sku' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->sku),
            'barcode' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->barcode),
            'quantity_available' => $this->quantity_available,
            'quantity_reserved' => $this->quantity_reserved,
            'quantity_damaged' => $this->quantity_damaged,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
