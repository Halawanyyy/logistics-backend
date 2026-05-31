<?php

namespace App\Modules\MerchantPortal\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalWarehouseProductDetailResource extends JsonResource
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

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'barcode_type' => $this->barcode_type,
            'status' => $this->status,
            'status_label' => $labels->label('warehouse.product_status', $this->status),
            'description' => $this->description,
            'is_fragile' => (bool) $this->is_fragile,
            'requires_packaging' => (bool) $this->requires_packaging,
            'minimum_stock_quantity' => $this->minimum_stock_quantity,
            'low_stock_alert_enabled' => (bool) $this->low_stock_alert_enabled,
            'current_stock_summary' => [
                'on_hand_quantity' => $available + $reserved,
                'available_quantity' => $available,
                'reserved_quantity' => $reserved,
                'damaged_quantity' => $damaged,
                'low_stock' => (bool) $this->low_stock_alert_enabled && $available <= (int) ($this->minimum_stock_quantity ?? 0),
            ],
            'inventory_stocks' => $stocks->map(fn ($stock) => [
                'id' => $stock->id,
                'warehouse' => [
                    'id' => $stock->warehouse_id,
                    'name' => $stock->warehouse?->name,
                ],
                'warehouse_bin' => $stock->warehouseBin !== null ? [
                    'id' => $stock->warehouse_bin_id,
                    'name' => $stock->warehouseBin?->name,
                    'code' => $stock->warehouseBin?->code,
                ] : null,
                'on_hand_quantity' => (int) $stock->quantity_available + (int) $stock->quantity_reserved,
                'available_quantity' => (int) $stock->quantity_available,
                'reserved_quantity' => (int) $stock->quantity_reserved,
                'damaged_quantity' => (int) $stock->quantity_damaged,
                'updated_at' => $stock->updated_at,
            ])->values(),
            'latest_movements_summary' => $this->relationLoaded('stockMovements')
                ? $this->stockMovements->take(10)->map(fn ($movement) => [
                    'id' => $movement->id,
                    'movement_type' => $movement->type,
                    'movement_type_label' => $labels->label('warehouse.stock_movement_type', $movement->type),
                    'quantity' => $movement->quantity,
                    'warehouse_name' => $movement->warehouse?->name,
                    'reference_type' => $movement->reference_type,
                    'reference_id' => $movement->reference_id,
                    'created_at' => $movement->created_at,
                ])->values()
                : [],
            'label_available' => true,
            'created_at' => $this->created_at,
        ];
    }
}
