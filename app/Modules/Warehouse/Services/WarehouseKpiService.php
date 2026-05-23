<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Settings\Services\SystemSettingService;
use App\Modules\Warehouse\Enums\PackingTaskStatus;
use App\Modules\Warehouse\Enums\PickingListStatus;
use App\Modules\Warehouse\Enums\WarehouseChargeType;
use App\Modules\Warehouse\Enums\WarehouseReturnCondition;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\PackingTask;
use App\Modules\Warehouse\Models\PickingList;
use App\Modules\Warehouse\Models\WarehouseCharge;
use App\Modules\Warehouse\Models\WarehouseReturn;

class WarehouseKpiService
{
    public function __construct(
        private readonly SystemSettingService $systemSettingService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(array $filters): array
    {
        $inventoryQuery = InventoryStock::query()
            ->when(isset($filters['warehouse_id']), fn ($query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['merchant_id']), fn ($query) => $query->whereHas('warehouseProduct', fn ($inner) => $inner->where('merchant_id', $filters['merchant_id'])));

        $pickingQuery = PickingList::query()
            ->when(isset($filters['warehouse_id']), fn ($query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['merchant_id']), fn ($query) => $query->whereHas('items.order', fn ($inner) => $inner->where('merchant_id', $filters['merchant_id'])))
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']));

        $packingQuery = PackingTask::query()
            ->when(isset($filters['warehouse_id']), fn ($query) => $query->whereHas('pickingList', fn ($inner) => $inner->where('warehouse_id', $filters['warehouse_id'])))
            ->when(isset($filters['merchant_id']), fn ($query) => $query->whereHas('order', fn ($inner) => $inner->where('merchant_id', $filters['merchant_id'])))
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']));

        $returnsQuery = WarehouseReturn::query()
            ->when(isset($filters['warehouse_id']), fn ($query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['merchant_id']), fn ($query) => $query->whereHas('warehouseProduct', fn ($inner) => $inner->where('merchant_id', $filters['merchant_id'])))
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']));

        $billingQuery = WarehouseCharge::query()
            ->when(isset($filters['warehouse_id']), fn ($query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['merchant_id']), fn ($query) => $query->where('merchant_id', $filters['merchant_id']))
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']));

        $inventory = $inventoryQuery->get();
        $pickings = $pickingQuery->get();
        $packings = $packingQuery->get();
        $returns = $returnsQuery->get();
        $charges = $billingQuery->get();
        $lowStockThreshold = max(0, (int) $this->systemSettingService->get('low_stock_threshold', 5));

        return [
            'inventory' => [
                'total_available' => (int) $inventory->sum('quantity_available'),
                'total_reserved' => (int) $inventory->sum('quantity_reserved'),
                'total_damaged' => (int) $inventory->sum('quantity_damaged'),
                'total_skus' => $inventory->pluck('warehouse_product_id')->unique()->count(),
                'low_stock_threshold' => $lowStockThreshold,
                'low_stock_skus' => $inventory->filter(
                    fn ($stock) => (int) $stock->quantity_available <= $lowStockThreshold
                )->pluck('warehouse_product_id')->unique()->count(),
            ],
            'operations' => [
                'picking_lists_total' => $pickings->count(),
                'picking_lists_completed' => $pickings->where('status', PickingListStatus::COMPLETED)->count(),
                'packing_tasks_total' => $packings->count(),
                'packing_tasks_packed' => $packings->where('status', PackingTaskStatus::PACKED)->count(),
            ],
            'returns' => [
                'total_returns' => $returns->count(),
                'sellable_returns' => $returns->where('condition', WarehouseReturnCondition::SELLABLE)->count(),
                'damaged_returns' => $returns->where('condition', WarehouseReturnCondition::DAMAGED)->count(),
                'disposed_returns' => $returns->where('condition', WarehouseReturnCondition::DISPOSED)->count(),
            ],
            'billing' => [
                'storage_charges' => number_format((float) $charges->where('type', WarehouseChargeType::STORAGE)->sum('amount'), 2, '.', ''),
                'packaging_charges' => number_format((float) $charges->where('type', WarehouseChargeType::PACKAGING)->sum('amount'), 2, '.', ''),
                'fulfillment_charges' => number_format((float) $charges->where('type', WarehouseChargeType::FULFILLMENT)->sum('amount'), 2, '.', ''),
            ],
        ];
    }
}
