<?php

namespace App\Modules\MerchantPortal\Services;

use App\Modules\Label\Services\LabelDataBuilderService;
use App\Modules\Label\Services\LabelRenderService;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\WarehouseCharge;
use App\Modules\Warehouse\Models\WarehouseProduct;
use App\Modules\Warehouse\Services\WarehouseProductBarcodeService;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\Response;

class MerchantPortalWarehouseService
{
    public function __construct(
        private readonly LabelDataBuilderService $labelDataBuilderService,
        private readonly LabelRenderService $labelRenderService,
        private readonly WarehouseProductBarcodeService $warehouseProductBarcodeService,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function productQueryForMerchant(int $merchantId, array $filters = []): Builder
    {
        return WarehouseProduct::query()
            ->with(['merchant', 'inventoryStocks.warehouse', 'inventoryStocks.warehouseBin'])
            ->where('merchant_id', $merchantId)
            ->when(isset($filters['warehouse_id']), fn ($query) => $query->whereHas('inventoryStocks', fn ($inner) => $inner->where('warehouse_id', $filters['warehouse_id'])))
            ->when(isset($filters['sku']), fn ($query) => $query->where('sku', 'like', '%'.$filters['sku'].'%'))
            ->when(isset($filters['barcode']), fn ($query) => $query->where('barcode', 'like', '%'.$filters['barcode'].'%'))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['created_from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['created_from']))
            ->when(isset($filters['created_to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['created_to']))
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];

                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%');
                });
            })
            ->when(($filters['has_stock'] ?? null) === true, fn ($query) => $query->whereHas('inventoryStocks', fn ($inner) => $inner->where(function ($stock) {
                $stock->where('quantity_available', '>', 0)->orWhere('quantity_reserved', '>', 0)->orWhere('quantity_damaged', '>', 0);
            })))
            ->when(($filters['has_stock'] ?? null) === false, fn ($query) => $query->whereDoesntHave('inventoryStocks', fn ($inner) => $inner->where(function ($stock) {
                $stock->where('quantity_available', '>', 0)->orWhere('quantity_reserved', '>', 0)->orWhere('quantity_damaged', '>', 0);
            })))
            ->when(($filters['low_stock'] ?? false) === true, function ($query) use ($filters) {
                $query->where('low_stock_alert_enabled', true)
                    ->whereHas('inventoryStocks', function ($inner) use ($filters) {
                        $inner->when(isset($filters['warehouse_id']), fn ($stockQuery) => $stockQuery->where('warehouse_id', $filters['warehouse_id']))
                            ->whereRaw('inventory_stocks.quantity_available <= warehouse_products.minimum_stock_quantity');
                    });
            })
            ->latest();
    }

    public function findOwnedProduct(int $merchantId, int $productId): WarehouseProduct
    {
        return WarehouseProduct::query()
            ->with(['merchant', 'inventoryStocks.warehouse', 'inventoryStocks.warehouseBin', 'stockMovements' => fn ($query) => $query->with('warehouse')->latest()])
            ->where('merchant_id', $merchantId)
            ->whereKey($productId)
            ->firstOrFail();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function inventoryQueryForMerchant(int $merchantId, array $filters = []): Builder
    {
        return InventoryStock::query()
            ->with(['warehouse', 'warehouseProduct.merchant', 'warehouseBin.zone', 'warehouseBin.shelf'])
            ->whereHas('warehouseProduct', fn ($query) => $query->where('merchant_id', $merchantId))
            ->when(isset($filters['warehouse_id']), fn ($query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['warehouse_product_id']), fn ($query) => $query->where('warehouse_product_id', $filters['warehouse_product_id']))
            ->when(isset($filters['sku']), fn ($query) => $query->whereHas('warehouseProduct', fn ($inner) => $inner->where('sku', 'like', '%'.$filters['sku'].'%')))
            ->when(isset($filters['barcode']), fn ($query) => $query->whereHas('warehouseProduct', fn ($inner) => $inner->where('barcode', 'like', '%'.$filters['barcode'].'%')))
            ->when(($filters['has_reserved'] ?? false) === true, fn ($query) => $query->where('quantity_reserved', '>', 0))
            ->when(($filters['available_only'] ?? false) === true, fn ($query) => $query->where('quantity_available', '>', 0))
            ->when(($filters['low_stock'] ?? false) === true, function ($query) {
                $query->whereRaw('quantity_available <= COALESCE((select minimum_stock_quantity from warehouse_products where warehouse_products.id = inventory_stocks.warehouse_product_id), 0)')
                    ->whereRaw('COALESCE((select low_stock_alert_enabled from warehouse_products where warehouse_products.id = inventory_stocks.warehouse_product_id), 0) = 1');
            })
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];

                $query->whereHas('warehouseProduct', function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%');
                });
            })
            ->latest();
    }

    public function findOwnedInventoryStock(int $merchantId, int $inventoryStockId): InventoryStock
    {
        return $this->inventoryQueryForMerchant($merchantId)
            ->whereKey($inventoryStockId)
            ->firstOrFail();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function movementQueryForMerchant(int $merchantId, array $filters = []): Builder
    {
        return StockMovement::query()
            ->with(['warehouse', 'warehouseProduct.merchant', 'order', 'shipment'])
            ->whereHas('warehouseProduct', fn ($query) => $query->where('merchant_id', $merchantId))
            ->when(isset($filters['warehouse_id']), fn ($query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['warehouse_product_id']), fn ($query) => $query->where('warehouse_product_id', $filters['warehouse_product_id']))
            ->when(isset($filters['movement_type']), fn ($query) => $query->where('type', $filters['movement_type']))
            ->when(isset($filters['reference_type']), fn ($query) => $query->where('reference_type', $filters['reference_type']))
            ->when(isset($filters['reference_id']), fn ($query) => $query->where('reference_id', $filters['reference_id']))
            ->when(isset($filters['order_id']), fn ($query) => $query->where('order_id', $filters['order_id']))
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['sku']), fn ($query) => $query->whereHas('warehouseProduct', fn ($inner) => $inner->where('sku', 'like', '%'.$filters['sku'].'%')))
            ->when(isset($filters['barcode']), fn ($query) => $query->whereHas('warehouseProduct', fn ($inner) => $inner->where('barcode', 'like', '%'.$filters['barcode'].'%')))
            ->latest();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function chargeQueryForMerchant(int $merchantId, array $filters = []): Builder
    {
        return WarehouseCharge::query()
            ->with(['warehouse', 'warehouseProduct', 'order'])
            ->where('merchant_id', $merchantId)
            ->when(isset($filters['warehouse_id']), fn ($query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['charge_type']), fn ($query) => $query->where('type', $filters['charge_type']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('charge_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('charge_date', '<=', $filters['date_to']))
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];

                $query->where(function ($inner) use ($search) {
                    $inner->where('description', 'like', '%'.$search.'%')
                        ->orWhere('type', 'like', '%'.$search.'%')
                        ->orWhereHas('warehouseProduct', fn ($productQuery) => $productQuery->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%'))
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('order_number', 'like', '%'.$search.'%'));
                });
            })
            ->latest();
    }

    public function renderOwnedProductLabels(array $items, array $options, int $merchantId): Response
    {
        $productIds = collect($items)->pluck('warehouse_product_id')->unique()->values()->all();
        $products = WarehouseProduct::query()
            ->with(['merchant', 'inventoryStocks.warehouse', 'inventoryStocks.warehouseBin'])
            ->where('merchant_id', $merchantId)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');
        $labels = [];

        foreach ($items as $item) {
            $product = $products->get((int) $item['warehouse_product_id']);

            if (! $product instanceof WarehouseProduct) {
                abort(404);
            }

            $product = $this->warehouseProductBarcodeService->ensureBarcode($product);
            $payload = $this->labelDataBuilderService->forWarehouseProduct($product);

            for ($index = 0; $index < (int) $item['quantity']; $index++) {
                $labels[] = $payload;
            }
        }

        return $this->labelRenderService->renderLabels($labels, 'warehouse_product', [
            ...$options,
            'filename' => 'merchant-warehouse-product-labels',
        ]);
    }
}
