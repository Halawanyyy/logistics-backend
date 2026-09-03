<?php

namespace App\Modules\Label\Controllers;

use App\Modules\Label\Requests\BulkShipmentLabelsRequest;
use App\Modules\Label\Requests\BulkWarehouseProductLabelsRequest;
use App\Modules\Label\Requests\RenderLabelRequest;
use App\Modules\Label\Services\LabelDataBuilderService;
use App\Modules\Label\Services\LabelRenderService;
use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Warehouse\Models\WarehouseBin;
use App\Modules\Warehouse\Models\WarehouseProduct;
use App\Modules\Warehouse\Services\WarehouseProductBarcodeService;
use App\Services\BarcodeService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResourceLabelController
{
    public function __construct(
        private readonly BarcodeService $barcodeService,
        private readonly LabelDataBuilderService $labelDataBuilderService,
        private readonly LabelRenderService $labelRenderService,
        private readonly WarehouseProductBarcodeService $warehouseProductBarcodeService,
    ) {}

    public function warehouseProductLabel(RenderLabelRequest $request, WarehouseProduct $warehouseProduct): Response
    {
        $product = $this->warehouseProductBarcodeService->ensureBarcode($warehouseProduct);

        return $this->labelRenderService->renderLabel(
            $this->labelDataBuilderService->forWarehouseProduct($product),
            [
                ...$request->validated(),
                'filename' => 'warehouse-product-'.$product->id,
            ]
        );
    }

    public function bulkWarehouseProductLabels(BulkWarehouseProductLabelsRequest $request): Response
    {
        $validated = $request->validated();
        $productIds = collect($validated['items'])->pluck('warehouse_product_id')->unique()->values()->all();
        $products = WarehouseProduct::query()
            ->with(['merchant', 'inventoryStocks.warehouse', 'inventoryStocks.warehouseBin'])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');
        $labels = [];

        foreach ($validated['items'] as $item) {
            $product = $products->get((int) $item['warehouse_product_id']);
            $product = $product instanceof WarehouseProduct ? $this->warehouseProductBarcodeService->ensureBarcode($product) : null;

            if (! $product instanceof WarehouseProduct) {
                abort(422, 'Warehouse product not found.');
            }

            $payload = $this->labelDataBuilderService->forWarehouseProduct($product);

            for ($index = 0; $index < (int) $item['quantity']; $index++) {
                $labels[] = $payload;
            }
        }

        return $this->labelRenderService->renderLabels($labels, 'warehouse_product', [
            ...$validated,
            'filename' => 'warehouse-product-labels',
        ]);
    }

    public function shipmentLabel(RenderLabelRequest $request, Shipment $shipment): Response
    {
        $shipment = $this->ensureShipmentBarcode($shipment);

        return $this->labelRenderService->renderLabel(
            $this->labelDataBuilderService->forShipment($shipment),
            [
                ...$request->validated(),
                'filename' => 'shipment-'.$shipment->id,
            ]
        );
    }

    public function shipmentWaybill(RenderLabelRequest $request, Shipment $shipment): Response
    {
        return $this->shipmentLabel($request, $shipment);
    }

    public function bulkShipmentLabels(BulkShipmentLabelsRequest $request): Response
    {
        $validated = $request->validated();
        $shipments = Shipment::query()
            ->with(['merchant', 'order', 'deliveryGovernorate', 'deliveryArea', 'waybill'])
            ->whereIn('id', $validated['shipment_ids'])
            ->get();
        $labels = [];

        foreach ($shipments as $shipment) {
            $labels[] = $this->labelDataBuilderService->forShipment($this->ensureShipmentBarcode($shipment));
        }

        return $this->labelRenderService->renderLabels($labels, 'shipment', [
            ...$validated,
            'filename' => 'shipment-labels',
        ]);
    }

    public function orderLabel(RenderLabelRequest $request, Order $order): Response
    {
        $order = $this->ensureOrderBarcode($order);

        return $this->labelRenderService->renderLabel(
            $this->labelDataBuilderService->forOrder($order),
            [
                ...$request->validated(),
                'filename' => 'order-'.$order->id,
            ]
        );
    }

    public function warehouseBinLabel(RenderLabelRequest $request, WarehouseBin $warehouseBin): Response
    {
        $warehouseBin = $this->ensureWarehouseBinBarcode($warehouseBin);

        return $this->labelRenderService->renderLabel(
            $this->labelDataBuilderService->forWarehouseBin($warehouseBin),
            [
                ...$request->validated(),
                'filename' => 'warehouse-bin-'.$warehouseBin->id,
            ]
        );
    }

    private function ensureOrderBarcode(Order $order): Order
    {
        if ($order->barcode !== null && $order->barcode !== '') {
            return $order->fresh()->load(['merchant', 'deliveryGovernorate', 'deliveryArea', 'items', 'waybill']);
        }

        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->barcode === null || $lockedOrder->barcode === '') {
                $lockedOrder->update([
                    'barcode' => $this->barcodeService->generateOrderBarcode(),
                ]);
            }

            return $lockedOrder->fresh()->load(['merchant', 'deliveryGovernorate', 'deliveryArea', 'items', 'waybill']);
        });
    }

    private function ensureShipmentBarcode(Shipment $shipment): Shipment
    {
        if ($shipment->barcode !== null && $shipment->barcode !== '') {
            return $shipment->fresh()->load(['merchant', 'order', 'deliveryGovernorate', 'deliveryArea', 'waybill']);
        }

        return DB::transaction(function () use ($shipment) {
            $lockedShipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);

            if ($lockedShipment->barcode === null || $lockedShipment->barcode === '') {
                $lockedShipment->update([
                    'barcode' => $this->barcodeService->generateShipmentBarcode(),
                ]);
            }

            return $lockedShipment->fresh()->load(['merchant', 'order', 'deliveryGovernorate', 'deliveryArea', 'waybill']);
        });
    }

    private function ensureWarehouseBinBarcode(WarehouseBin $warehouseBin): WarehouseBin
    {
        if ($warehouseBin->barcode !== null && $warehouseBin->barcode !== '') {
            return $warehouseBin->fresh()->load(['warehouse', 'zone', 'shelf']);
        }

        return DB::transaction(function () use ($warehouseBin) {
            $lockedBin = WarehouseBin::query()->lockForUpdate()->findOrFail($warehouseBin->id);

            if ($lockedBin->barcode === null || $lockedBin->barcode === '') {
                $lockedBin->update([
                    'barcode' => $this->barcodeService->generateWarehouseBinBarcode(),
                ]);
            }

            return $lockedBin->fresh()->load(['warehouse', 'zone', 'shelf']);
        });
    }
}
