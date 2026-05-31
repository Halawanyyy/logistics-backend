<?php

namespace App\Modules\Warehouse\Controllers;

use App\Modules\Warehouse\Models\WarehouseReceipt;
use App\Modules\Warehouse\Requests\ListWarehouseReceiptsRequest;
use App\Modules\Warehouse\Requests\StoreWarehouseReceiptRequest;
use App\Modules\Warehouse\Requests\UpdateWarehouseReceiptRequest;
use App\Modules\Warehouse\Resources\WarehouseReceiptResource;
use App\Modules\Warehouse\Services\WarehouseReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseReceiptController
{
    public function __construct(
        private readonly WarehouseReceiptService $warehouseReceiptService,
    ) {
    }

    public function index(ListWarehouseReceiptsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $receipts = WarehouseReceipt::query()
            ->with($this->relations())
            ->when(isset($validated['merchant_id']), fn ($query) => $query->where('merchant_id', $validated['merchant_id']))
            ->when(isset($validated['warehouse_id']), fn ($query) => $query->where('warehouse_id', $validated['warehouse_id']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['receipt_number']), fn ($query) => $query->where('receipt_number', 'like', '%'.$validated['receipt_number'].'%'))
            ->when(isset($validated['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $validated['date_to']))
            ->latest()
            ->get();

        return WarehouseReceiptResource::collection($receipts);
    }

    public function store(StoreWarehouseReceiptRequest $request): JsonResponse
    {
        $receipt = $this->warehouseReceiptService->store($request->validated(), $request->user()?->id);

        return (new WarehouseReceiptResource($receipt))
            ->response()
            ->setStatusCode(201);
    }

    public function show(WarehouseReceipt $warehouseReceipt): WarehouseReceiptResource
    {
        return new WarehouseReceiptResource($warehouseReceipt->load($this->relations()));
    }

    public function update(UpdateWarehouseReceiptRequest $request, WarehouseReceipt $warehouseReceipt): WarehouseReceiptResource
    {
        return new WarehouseReceiptResource($this->warehouseReceiptService->update($warehouseReceipt, $request->validated()));
    }

    public function confirm(WarehouseReceipt $warehouseReceipt): WarehouseReceiptResource
    {
        return new WarehouseReceiptResource($this->warehouseReceiptService->confirm($warehouseReceipt, auth()->id()));
    }

    public function cancel(WarehouseReceipt $warehouseReceipt): WarehouseReceiptResource
    {
        return new WarehouseReceiptResource($this->warehouseReceiptService->cancel($warehouseReceipt));
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'merchant',
            'warehouse',
            'receivedBy',
            'confirmedBy',
            'items.warehouseProduct',
        ];
    }
}
