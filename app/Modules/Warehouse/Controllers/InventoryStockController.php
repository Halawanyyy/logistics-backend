<?php

namespace App\Modules\Warehouse\Controllers;

use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Requests\AdjustStockRequest;
use App\Modules\Warehouse\Requests\ListInventoryStocksRequest;
use App\Modules\Warehouse\Resources\InventoryStockResource;
use App\Modules\Warehouse\Services\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryStockController
{
    public function __construct(
        private readonly StockAdjustmentService $stockAdjustmentService,
    ) {
    }

    public function index(ListInventoryStocksRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $stocks = InventoryStock::query()
            ->with(['warehouse', 'warehouseProduct.merchant', 'warehouseBin.zone', 'warehouseBin.shelf'])
            ->when(isset($validated['warehouse_id']), fn ($query) => $query->where('warehouse_id', $validated['warehouse_id']))
            ->when(isset($validated['warehouse_product_id']), fn ($query) => $query->where('warehouse_product_id', $validated['warehouse_product_id']))
            ->when(isset($validated['merchant_id']), fn ($query) => $query->whereHas('warehouseProduct', fn ($inner) => $inner->where('merchant_id', $validated['merchant_id'])))
            ->when(isset($validated['warehouse_bin_id']), fn ($query) => $query->where('warehouse_bin_id', $validated['warehouse_bin_id']))
            ->when(($validated['low_stock'] ?? false) === true, fn ($query) => $query->where('quantity_available', '<=', 5))
            ->latest()
            ->get();

        return InventoryStockResource::collection($stocks);
    }

    public function show(InventoryStock $inventoryStock): InventoryStockResource
    {
        return new InventoryStockResource($inventoryStock->load(['warehouse', 'warehouseProduct.merchant', 'warehouseBin.zone', 'warehouseBin.shelf']));
    }

    public function adjust(AdjustStockRequest $request): JsonResponse
    {
        $stock = $this->stockAdjustmentService->adjust(
            $request->integer('warehouse_id'),
            $request->integer('warehouse_product_id'),
            $request->input('warehouse_bin_id'),
            $request->string('type')->toString(),
            $request->integer('quantity'),
            $request->input('notes'),
            $request->user()?->id,
        );

        return (new InventoryStockResource($stock->load(['warehouse', 'warehouseProduct.merchant', 'warehouseBin.zone', 'warehouseBin.shelf'])))
            ->response()
            ->setStatusCode(201);
    }
}
