<?php

namespace App\Modules\Warehouse\Controllers;

use App\Modules\Warehouse\Models\WarehouseProduct;
use App\Modules\Warehouse\Requests\BulkWarehouseBarcodeLabelsRequest;
use App\Modules\Warehouse\Requests\ListWarehouseProductsRequest;
use App\Modules\Warehouse\Requests\StoreWarehouseProductRequest;
use App\Modules\Warehouse\Requests\UpdateWarehouseProductRequest;
use App\Modules\Warehouse\Resources\WarehouseProductResource;
use App\Services\BarcodeService;
use App\Modules\Warehouse\Services\WarehouseProductBarcodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseProductController
{
    public function __construct(
        private readonly BarcodeService $barcodeService,
        private readonly WarehouseProductBarcodeService $warehouseProductBarcodeService,
    ) {
    }

    public function index(ListWarehouseProductsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $products = $this->buildProductsQuery($validated)->latest()->get();

        return WarehouseProductResource::collection($products);
    }

    public function lowStock(ListWarehouseProductsRequest $request): AnonymousResourceCollection
    {
        $validated = array_merge($request->validated(), ['low_stock' => true]);

        return WarehouseProductResource::collection(
            $this->buildProductsQuery($validated)->latest()->get()
        );
    }

    public function store(StoreWarehouseProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'active';
        $data['is_fragile'] = $request->boolean('is_fragile');
        $data['requires_packaging'] = $request->boolean('requires_packaging');
        $data['low_stock_alert_enabled'] = $request->boolean('low_stock_alert_enabled');
        $data['barcode'] = $data['barcode'] ?? $this->barcodeService->generateWarehouseProductBarcode((int) $data['merchant_id']);
        $data['barcode_type'] = $data['barcode_type'] ?? 'code128';
        $data['barcode_generated_at'] = $data['barcode'] !== null ? now() : null;

        $product = WarehouseProduct::create($data);

        return (new WarehouseProductResource($product->load('merchant')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(WarehouseProduct $warehouseProduct): WarehouseProductResource
    {
        return new WarehouseProductResource($warehouseProduct->load('merchant'));
    }

    public function update(UpdateWarehouseProductRequest $request, WarehouseProduct $warehouseProduct): WarehouseProductResource
    {
        $data = $request->validated();

        foreach (['is_fragile', 'requires_packaging'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->boolean($field);
            }
        }

        if ($request->has('low_stock_alert_enabled')) {
            $data['low_stock_alert_enabled'] = $request->boolean('low_stock_alert_enabled');
        }

        if (array_key_exists('barcode', $data) && $data['barcode'] !== $warehouseProduct->barcode && $data['barcode'] !== null) {
            $data['barcode_generated_at'] = $warehouseProduct->barcode_generated_at ?? now();
        }

        $warehouseProduct->update($data);

        return new WarehouseProductResource($warehouseProduct->fresh()->load('merchant'));
    }

    public function generateBarcode(WarehouseProduct $warehouseProduct): WarehouseProductResource
    {
        return new WarehouseProductResource($this->warehouseProductBarcodeService->ensureBarcode($warehouseProduct));
    }

    public function barcode(WarehouseProduct $warehouseProduct): JsonResponse
    {
        $product = $this->warehouseProductBarcodeService->ensureBarcode($warehouseProduct);

        return response()->json([
            'data' => [
                'warehouse_product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'barcode_type' => $product->barcode_type,
                'barcode_generated_at' => $product->barcode_generated_at,
            ],
        ]);
    }

    public function barcodeLabel(WarehouseProduct $warehouseProduct): \Symfony\Component\HttpFoundation\Response
    {
        $product = $this->warehouseProductBarcodeService->ensureBarcode($warehouseProduct->load('merchant'));
        $html = $this->warehouseProductBarcodeService->renderSingleLabel($product);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function bulkBarcodeLabels(BulkWarehouseBarcodeLabelsRequest $request): \Symfony\Component\HttpFoundation\Response
    {
        $html = $this->warehouseProductBarcodeService->buildBulkLabels($request->validated()['items']);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function destroy(WarehouseProduct $warehouseProduct): JsonResponse
    {
        $warehouseProduct->delete();

        return response()->json([
            'message' => 'Warehouse product deleted successfully',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function buildProductsQuery(array $validated)
    {
        return WarehouseProduct::query()
            ->with('merchant')
            ->when(isset($validated['merchant_id']), fn ($query) => $query->where('merchant_id', $validated['merchant_id']))
            ->when(isset($validated['warehouse_id']), fn ($query) => $query->whereHas('inventoryStocks', fn ($inner) => $inner->where('warehouse_id', $validated['warehouse_id'])))
            ->when(isset($validated['sku']), fn ($query) => $query->where('sku', 'like', '%'.$validated['sku'].'%'))
            ->when(isset($validated['barcode']), fn ($query) => $query->where('barcode', 'like', '%'.$validated['barcode'].'%'))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['search']), function ($query) use ($validated) {
                $search = $validated['search'];

                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%');
                });
            })
            ->when(($validated['low_stock'] ?? false) === true, function ($query) use ($validated) {
                $query->where('low_stock_alert_enabled', true)
                    ->whereHas('inventoryStocks', function ($inner) use ($validated) {
                        $inner->when(isset($validated['warehouse_id']), fn ($stockQuery) => $stockQuery->where('warehouse_id', $validated['warehouse_id']))
                            ->whereRaw('inventory_stocks.quantity_available <= warehouse_products.minimum_stock_quantity');
                    });
            });
    }
}
