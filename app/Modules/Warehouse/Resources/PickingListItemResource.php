<?php

namespace App\Modules\Warehouse\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PickingListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'order_item_id' => $this->order_item_id,
            'product_name' => $this->whenLoaded('orderItem', fn () => $this->orderItem?->product_name),
            'warehouse_product_id' => $this->warehouse_product_id,
            'warehouse_product_name' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->name),
            'warehouse_product_barcode' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->barcode),
            'warehouse_bin_id' => $this->warehouse_bin_id,
            'bin_name' => $this->whenLoaded('warehouseBin', fn () => $this->warehouseBin?->name),
            'bin_code' => $this->whenLoaded('warehouseBin', fn () => $this->warehouseBin?->code),
            'bin_barcode' => $this->whenLoaded('warehouseBin', fn () => $this->warehouseBin?->barcode),
            'quantity_required' => $this->quantity_required,
            'quantity_picked' => $this->quantity_picked,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
