<?php

namespace App\Modules\Barcode\Controllers;

use App\Services\BarcodeService;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Order\Resources\OrderResource;
use App\Modules\Shipment\Services\ShipmentStatusUpdateService;
use App\Modules\Shipment\Resources\ShipmentResource;
use App\Modules\Warehouse\Models\WarehouseProduct;
use App\Modules\Warehouse\Resources\InventoryStockResource;
use App\Modules\Warehouse\Resources\WarehouseProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BarcodeController
{
    public function __construct(
        private readonly BarcodeService $barcodeService,
        private readonly ShipmentStatusUpdateService $shipmentStatusUpdateService,
    ) {
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->barcodeService->lookup($validated['barcode']);

        if ($result === null) {
            return response()->json([
                'message' => 'Barcode not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'type' => $result['type'],
                'id' => $result['model']->getKey(),
                'barcode' => $validated['barcode'],
                'data' => $this->resourcePayload($result['type'], $result['model']),
            ],
        ]);
    }

    public function scanShipmentStatus(Request $request): ShipmentResource|JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(ShipmentStatus::values())],
            'notes' => ['nullable', 'string'],
        ]);

        $result = $this->barcodeService->lookup($validated['barcode']);

        if ($result === null || $result['type'] !== 'shipment') {
            return response()->json([
                'message' => 'Barcode not found.',
            ], 404);
        }

        $shipment = $this->shipmentStatusUpdateService->update(
            $result['model'],
            $validated['status'],
            $validated['notes'] ?? null,
            $request->user()?->id,
        );

        return new ShipmentResource($shipment->load([
            'merchant',
            'order',
            'deliveryGovernorate',
            'deliveryArea',
            'assignedDriver',
            'histories',
            'histories.changedBy',
            'itemResults',
            'itemResults.orderItem',
        ]));
    }

    public function scanProductLookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->barcodeService->lookup($validated['barcode']);

        if ($result === null || $result['type'] !== 'warehouse_product') {
            return response()->json([
                'message' => 'Barcode not found.',
            ], 404);
        }

        /** @var WarehouseProduct $product */
        $product = $result['model']->load([
            'merchant',
            'inventoryStocks.warehouse',
            'inventoryStocks.warehouseBin.warehouse',
            'inventoryStocks.warehouseBin.zone',
            'inventoryStocks.warehouseBin.shelf',
        ]);

        return response()->json([
            'data' => [
                'type' => 'warehouse_product',
                'id' => $product->id,
                'barcode' => $product->barcode,
                'product' => (new WarehouseProductResource($product))->toArray($request),
                'inventory_stocks' => InventoryStockResource::collection($product->inventoryStocks)->resolve($request),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resourcePayload(string $type, mixed $model): array
    {
        return match ($type) {
            'order' => (new OrderResource($model->load([
                'merchant',
                'deliveryGovernorate',
                'deliveryArea',
                'originalOrder',
                'shipment',
                'latestStockReservation',
                'items',
            ])))->resolve(),
            'shipment' => (new ShipmentResource($model->load([
                'merchant',
                'order',
                'deliveryGovernorate',
                'deliveryArea',
                'assignedDriver',
                'histories',
                'histories.changedBy',
                'itemResults',
                'itemResults.orderItem',
            ])))->resolve(),
            'warehouse_product' => (new WarehouseProductResource($model->load('merchant')))->resolve(),
            default => [],
        };
    }
}
