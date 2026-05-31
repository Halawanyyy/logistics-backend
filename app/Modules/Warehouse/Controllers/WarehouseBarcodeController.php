<?php

namespace App\Modules\Warehouse\Controllers;

use App\Modules\Warehouse\Requests\ScanWarehouseBarcodeRequest;
use App\Services\BarcodeService;
use Illuminate\Http\JsonResponse;

class WarehouseBarcodeController
{
    public function __construct(
        private readonly BarcodeService $barcodeService,
    ) {
    }

    public function scan(ScanWarehouseBarcodeRequest $request): JsonResponse
    {
        $lookup = $this->barcodeService->lookup($request->string('barcode')->toString());

        if ($lookup === null || $lookup['type'] !== 'warehouse_product') {
            return response()->json([
                'message' => 'Barcode not found.',
            ], 404);
        }

        $product = $lookup['model']->load(['merchant', 'inventoryStocks.warehouse']);
        $stockSummary = $product->inventoryStocks->map(fn ($stock) => [
            'inventory_stock_id' => $stock->id,
            'warehouse_id' => $stock->warehouse_id,
            'warehouse_name' => $stock->warehouse?->name,
            'available_quantity' => $stock->quantity_available,
            'reserved_quantity' => $stock->quantity_reserved,
            'damaged_quantity' => $stock->quantity_damaged,
            'on_hand_quantity' => (int) $stock->quantity_available + (int) $stock->quantity_reserved,
        ])->values();

        return response()->json([
            'data' => [
                'warehouse_product' => [
                    'id' => $product->id,
                    'merchant_id' => $product->merchant_id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'status' => $product->status,
                ],
                'merchant' => [
                    'id' => $product->merchant?->id,
                    'name' => $product->merchant?->name,
                ],
                'stock_summary' => [
                    'total_available_quantity' => (int) $product->inventoryStocks->sum('quantity_available'),
                    'total_reserved_quantity' => (int) $product->inventoryStocks->sum('quantity_reserved'),
                    'total_damaged_quantity' => (int) $product->inventoryStocks->sum('quantity_damaged'),
                    'total_on_hand_quantity' => (int) $product->inventoryStocks->sum(fn ($stock) => (int) $stock->quantity_available + (int) $stock->quantity_reserved),
                    'warehouses' => $stockSummary,
                ],
                'possible_actions' => [
                    'receiving',
                    'picking',
                    'packing',
                    'return_inspection',
                    'manual_stock_lookup',
                ],
            ],
        ]);
    }
}
