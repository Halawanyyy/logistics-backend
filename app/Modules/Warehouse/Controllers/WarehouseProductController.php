<?php

namespace App\Modules\Warehouse\Controllers;

use App\Modules\Warehouse\Models\WarehouseProduct;
use App\Modules\Warehouse\Requests\ListWarehouseProductsRequest;
use App\Modules\Warehouse\Requests\StoreWarehouseProductRequest;
use App\Modules\Warehouse\Requests\UpdateWarehouseProductRequest;
use App\Modules\Warehouse\Resources\WarehouseProductResource;
use App\Services\BarcodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseProductController
{
    public function __construct(
        private readonly BarcodeService $barcodeService,
    ) {
    }

    public function index(ListWarehouseProductsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $products = WarehouseProduct::query()
            ->with('merchant')
            ->when(isset($validated['merchant_id']), fn ($query) => $query->where('merchant_id', $validated['merchant_id']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['search']), function ($query) use ($validated) {
                $search = $validated['search'];

                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->get();

        return WarehouseProductResource::collection($products);
    }

    public function store(StoreWarehouseProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'active';
        $data['is_fragile'] = $request->boolean('is_fragile');
        $data['requires_packaging'] = $request->boolean('requires_packaging');
        $data['barcode'] = $data['barcode'] ?? $this->barcodeService->generateWarehouseProductBarcode((int) $data['merchant_id']);

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

        $warehouseProduct->update($data);

        return new WarehouseProductResource($warehouseProduct->fresh()->load('merchant'));
    }

    public function destroy(WarehouseProduct $warehouseProduct): JsonResponse
    {
        $warehouseProduct->delete();

        return response()->json([
            'message' => 'Warehouse product deleted successfully',
        ]);
    }
}
