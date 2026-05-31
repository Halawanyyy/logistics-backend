<?php

namespace App\Modules\MerchantPortal\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalWarehouseProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);
        $stocks = $this->relationLoaded('inventoryStocks') ? $this->inventoryStocks : collect();
        $available = (int) $stocks->sum('quantity_available');
        $reserved = (int) $stocks->sum('quantity_reserved');
        $damaged = (int) $stocks->sum('quantity_damaged');
        $onHand = $available + $reserved;
        $lowStock = (bool) $this->low_stock_alert_enabled && $available <= (int) ($this->minimum_stock_quantity ?? 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'status' => $this->status,
            'status_label' => $labels->label('warehouse.product_status', $this->status),
            'merchant_summary' => [
                'id' => $this->merchant_id,
                'name' => $this->relationLoaded('merchant') ? $this->merchant?->name : null,
            ],
            'inventory_summary' => [
                'on_hand_quantity' => $onHand,
                'available_quantity' => $available,
                'reserved_quantity' => $reserved,
                'damaged_quantity' => $damaged,
                'warehouses_count' => $stocks->count(),
            ],
            'low_stock' => $lowStock,
            'created_at' => $this->created_at,
        ];
    }
}
