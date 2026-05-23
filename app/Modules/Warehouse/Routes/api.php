<?php

use App\Modules\Warehouse\Controllers\InventoryStockController;
use App\Modules\Warehouse\Controllers\MerchantWarehouseController;
use App\Modules\Warehouse\Controllers\OrderStockReservationController;
use App\Modules\Warehouse\Controllers\PackingTaskController;
use App\Modules\Warehouse\Controllers\PickPackFeeRuleController;
use App\Modules\Warehouse\Controllers\PickingListController;
use App\Modules\Warehouse\Controllers\StockMovementController;
use App\Modules\Warehouse\Controllers\StorageFeeRuleController;
use App\Modules\Warehouse\Controllers\WarehouseBinController;
use App\Modules\Warehouse\Controllers\WarehouseBillingAutomationController;
use App\Modules\Warehouse\Controllers\WarehouseChargeController;
use App\Modules\Warehouse\Controllers\WarehouseController;
use App\Modules\Warehouse\Controllers\WarehouseKpiController;
use App\Modules\Warehouse\Controllers\WarehouseProductController;
use App\Modules\Warehouse\Controllers\WarehouseReturnController;
use App\Modules\Warehouse\Controllers\WarehouseShelfController;
use App\Modules\Warehouse\Controllers\WarehouseZoneController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('warehouses')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])->middleware('permission:warehouses.view');
        Route::post('/', [WarehouseController::class, 'store'])->middleware('permission:warehouses.create');
        Route::get('/{warehouse}', [WarehouseController::class, 'show'])->middleware('permission:warehouses.view');
        Route::put('/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:warehouses.update');
        Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:warehouses.delete');
    });

    Route::prefix('warehouse-products')->group(function () {
        Route::get('/', [WarehouseProductController::class, 'index'])->middleware('permission:warehouse_products.view');
        Route::post('/', [WarehouseProductController::class, 'store'])->middleware('permission:warehouse_products.create');
        Route::get('/{warehouseProduct}', [WarehouseProductController::class, 'show'])->middleware('permission:warehouse_products.view');
        Route::put('/{warehouseProduct}', [WarehouseProductController::class, 'update'])->middleware('permission:warehouse_products.update');
        Route::delete('/{warehouseProduct}', [WarehouseProductController::class, 'destroy'])->middleware('permission:warehouse_products.delete');
    });

    Route::prefix('warehouse-zones')->group(function () {
        Route::get('/', [WarehouseZoneController::class, 'index'])->middleware('permission:warehouse_locations.view');
        Route::post('/', [WarehouseZoneController::class, 'store'])->middleware('permission:warehouse_locations.create');
        Route::get('/{warehouseZone}', [WarehouseZoneController::class, 'show'])->middleware('permission:warehouse_locations.view');
        Route::put('/{warehouseZone}', [WarehouseZoneController::class, 'update'])->middleware('permission:warehouse_locations.update');
        Route::delete('/{warehouseZone}', [WarehouseZoneController::class, 'destroy'])->middleware('permission:warehouse_locations.delete');
    });

    Route::prefix('warehouse-shelves')->group(function () {
        Route::get('/', [WarehouseShelfController::class, 'index'])->middleware('permission:warehouse_locations.view');
        Route::post('/', [WarehouseShelfController::class, 'store'])->middleware('permission:warehouse_locations.create');
        Route::get('/{warehouseShelf}', [WarehouseShelfController::class, 'show'])->middleware('permission:warehouse_locations.view');
        Route::put('/{warehouseShelf}', [WarehouseShelfController::class, 'update'])->middleware('permission:warehouse_locations.update');
        Route::delete('/{warehouseShelf}', [WarehouseShelfController::class, 'destroy'])->middleware('permission:warehouse_locations.delete');
    });

    Route::prefix('warehouse-bins')->group(function () {
        Route::get('/', [WarehouseBinController::class, 'index'])->middleware('permission:warehouse_locations.view');
        Route::post('/', [WarehouseBinController::class, 'store'])->middleware('permission:warehouse_locations.create');
        Route::get('/{warehouseBin}', [WarehouseBinController::class, 'show'])->middleware('permission:warehouse_locations.view');
        Route::put('/{warehouseBin}', [WarehouseBinController::class, 'update'])->middleware('permission:warehouse_locations.update');
        Route::delete('/{warehouseBin}', [WarehouseBinController::class, 'destroy'])->middleware('permission:warehouse_locations.delete');
    });

    Route::prefix('inventory-stocks')->group(function () {
        Route::get('/', [InventoryStockController::class, 'index'])->middleware('permission:inventory.view');
        Route::post('/adjust', [InventoryStockController::class, 'adjust'])->middleware('permission:inventory.adjust');
        Route::get('/{inventoryStock}', [InventoryStockController::class, 'show'])->middleware('permission:inventory.view');
    });

    Route::prefix('stock-movements')->group(function () {
        Route::get('/', [StockMovementController::class, 'index'])->middleware('permission:inventory.view');
        Route::get('/{stockMovement}', [StockMovementController::class, 'show'])->middleware('permission:inventory.view');
    });

    Route::prefix('warehouse-returns')->group(function () {
        Route::get('/', [WarehouseReturnController::class, 'index'])->middleware('permission:warehouse_returns.view');
        Route::post('/', [WarehouseReturnController::class, 'store'])->middleware('permission:warehouse_returns.create');
        Route::get('/{warehouseReturn}', [WarehouseReturnController::class, 'show'])->middleware('permission:warehouse_returns.view');
    });

    Route::prefix('warehouse-charges')->group(function () {
        Route::get('/', [WarehouseChargeController::class, 'index'])->middleware('permission:warehouse_charges.view');
        Route::post('/', [WarehouseChargeController::class, 'store'])->middleware('permission:warehouse_charges.create');
        Route::get('/{warehouseCharge}', [WarehouseChargeController::class, 'show'])->middleware('permission:warehouse_charges.view');
        Route::put('/{warehouseCharge}', [WarehouseChargeController::class, 'update'])->middleware('permission:warehouse_charges.update');
        Route::delete('/{warehouseCharge}', [WarehouseChargeController::class, 'destroy'])->middleware('permission:warehouse_charges.delete');
    });

    Route::prefix('picking-lists')->group(function () {
        Route::get('/', [PickingListController::class, 'index'])->middleware('permission:picking_lists.view');
        Route::post('/generate', [PickingListController::class, 'generate'])->middleware('permission:picking_lists.create');
        Route::get('/{pickingList}', [PickingListController::class, 'show'])->middleware('permission:picking_lists.view');
        Route::post('/{pickingList}/scan-pick', [PickingListController::class, 'scanPick'])->middleware('permission:picking_lists.scan');
        Route::post('/{pickingList}/cancel', [PickingListController::class, 'cancel'])->middleware('permission:picking_lists.cancel');
    });

    Route::prefix('packing-tasks')->group(function () {
        Route::get('/', [PackingTaskController::class, 'index'])->middleware('permission:packing_tasks.view');
        Route::post('/generate', [PackingTaskController::class, 'generate'])->middleware('permission:packing_tasks.create');
        Route::get('/{packingTask}', [PackingTaskController::class, 'show'])->middleware('permission:packing_tasks.view');
        Route::post('/{packingTask}/complete', [PackingTaskController::class, 'complete'])->middleware('permission:packing_tasks.complete');
    });

    Route::prefix('storage-fee-rules')->group(function () {
        Route::get('/', [StorageFeeRuleController::class, 'index'])->middleware('permission:storage_fee_rules.view');
        Route::post('/', [StorageFeeRuleController::class, 'store'])->middleware('permission:storage_fee_rules.create');
        Route::get('/{storageFeeRule}', [StorageFeeRuleController::class, 'show'])->middleware('permission:storage_fee_rules.view');
        Route::put('/{storageFeeRule}', [StorageFeeRuleController::class, 'update'])->middleware('permission:storage_fee_rules.update');
        Route::delete('/{storageFeeRule}', [StorageFeeRuleController::class, 'destroy'])->middleware('permission:storage_fee_rules.delete');
    });

    Route::prefix('pick-pack-fee-rules')->group(function () {
        Route::get('/', [PickPackFeeRuleController::class, 'index'])->middleware('permission:pick_pack_fee_rules.view');
        Route::post('/', [PickPackFeeRuleController::class, 'store'])->middleware('permission:pick_pack_fee_rules.create');
        Route::get('/{pickPackFeeRule}', [PickPackFeeRuleController::class, 'show'])->middleware('permission:pick_pack_fee_rules.view');
        Route::put('/{pickPackFeeRule}', [PickPackFeeRuleController::class, 'update'])->middleware('permission:pick_pack_fee_rules.update');
        Route::delete('/{pickPackFeeRule}', [PickPackFeeRuleController::class, 'destroy'])->middleware('permission:pick_pack_fee_rules.delete');
    });

    Route::post('/storage-fees/generate', [WarehouseBillingAutomationController::class, 'generateStorageFees'])->middleware('permission:storage_fees.generate');
    Route::post('/pick-pack-fees/generate', [WarehouseBillingAutomationController::class, 'generatePickPackFees'])->middleware('permission:pick_pack_fees.generate');
    Route::get('/warehouse/kpis', [WarehouseKpiController::class, 'index'])->middleware('permission:warehouse_kpis.view');

    Route::prefix('orders')->group(function () {
        Route::post('/{order}/reserve-stock', [OrderStockReservationController::class, 'reserve'])->middleware('permission:inventory.reserve');
        Route::post('/{order}/release-stock', [OrderStockReservationController::class, 'release'])->middleware('permission:inventory.release');
        Route::post('/{order}/fulfill-from-warehouse', [OrderStockReservationController::class, 'fulfill'])->middleware('permission:inventory.fulfill');
        Route::get('/{order}/stock-reservations', [OrderStockReservationController::class, 'orderReservations'])->middleware('permission:inventory.view');
    });

    Route::get('/stock-reservations', [OrderStockReservationController::class, 'index'])->middleware('permission:inventory.view');

    Route::get('/merchants/{merchant}/warehouse/inventory', [MerchantWarehouseController::class, 'inventory'])->middleware('permission:inventory.view');
    Route::get('/merchants/{merchant}/warehouse/movements', [MerchantWarehouseController::class, 'movements'])->middleware('permission:inventory.view');
    Route::get('/merchants/{merchant}/warehouse/charges', [MerchantWarehouseController::class, 'charges'])->middleware('permission:warehouse_charges.view');
});
