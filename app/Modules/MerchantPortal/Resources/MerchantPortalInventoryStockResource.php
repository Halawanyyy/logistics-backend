<?php

namespace App\Modules\MerchantPortal\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalInventoryStockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $available = (int) $this->quantity_available;
        $reserved = (int) $this->quantity_reserved;
        $damaged = (int) $this->quantity_damaged;

        return [
            'id' => $this->id,
            'warehouse_summary' => [
                'id' => $this->warehouse_id,
                'name' => $this->relationLoaded('warehouse') ? $this->warehouse?->name : null,
            ],
            'product_summary' => [
                'id' => $this->warehouse_product_id,
                'name' => $this->relationLoaded('warehouseProduct') ? $this->warehouseProduct?->name : null,
                'sku' => $this->relationLoaded('warehouseProduct') ? $this->warehouseProduct?->sku : null,
                'barcode' => $this->relationLoaded('warehouseProduct') ? $this->warehouseProduct?->barcode : null,
            ],
            'on_hand_quantity' => $available + $reserved,
            'reserved_quantity' => $reserved,
            'available_quantity' => $available,
            'damaged_quantity' => $damaged,
            'low_stock' => $this->relationLoaded('warehouseProduct')
                ? ((bool) $this->warehouseProduct?->low_stock_alert_enabled && $available <= (int) ($this->warehouseProduct?->minimum_stock_quantity ?? 0))
                : false,
            'updated_at' => $this->updated_at,
        ];
    }
}
