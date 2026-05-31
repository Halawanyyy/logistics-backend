<?php

namespace App\Modules\Warehouse\Controllers;

use App\Modules\Warehouse\Models\WarehouseTransfer;
use App\Modules\Warehouse\Requests\ListWarehouseTransfersRequest;
use App\Modules\Warehouse\Requests\ReceiveWarehouseTransferRequest;
use App\Modules\Warehouse\Requests\StoreWarehouseTransferRequest;
use App\Modules\Warehouse\Requests\UpdateWarehouseTransferRequest;
use App\Modules\Warehouse\Resources\WarehouseTransferResource;
use App\Modules\Warehouse\Services\WarehouseTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseTransferController
{
    public function __construct(
        private readonly WarehouseTransferService $warehouseTransferService,
    ) {
    }

    public function index(ListWarehouseTransfersRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $transfers = WarehouseTransfer::query()
            ->with($this->relations())
            ->when(isset($validated['source_warehouse_id']), fn ($query) => $query->where('source_warehouse_id', $validated['source_warehouse_id']))
            ->when(isset($validated['destination_warehouse_id']), fn ($query) => $query->where('destination_warehouse_id', $validated['destination_warehouse_id']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['transfer_number']), fn ($query) => $query->where('transfer_number', 'like', '%'.$validated['transfer_number'].'%'))
            ->when(isset($validated['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $validated['date_to']))
            ->latest()
            ->get();

        return WarehouseTransferResource::collection($transfers);
    }

    public function store(StoreWarehouseTransferRequest $request): JsonResponse
    {
        $transfer = $this->warehouseTransferService->store($request->validated(), $request->user()?->id);

        return (new WarehouseTransferResource($transfer))
            ->response()
            ->setStatusCode(201);
    }

    public function show(WarehouseTransfer $warehouseTransfer): WarehouseTransferResource
    {
        return new WarehouseTransferResource($warehouseTransfer->load($this->relations()));
    }

    public function update(UpdateWarehouseTransferRequest $request, WarehouseTransfer $warehouseTransfer): WarehouseTransferResource
    {
        return new WarehouseTransferResource($this->warehouseTransferService->update($warehouseTransfer, $request->validated()));
    }

    public function confirm(WarehouseTransfer $warehouseTransfer): WarehouseTransferResource
    {
        return new WarehouseTransferResource($this->warehouseTransferService->confirm($warehouseTransfer, auth()->id()));
    }

    public function dispatch(WarehouseTransfer $warehouseTransfer): WarehouseTransferResource
    {
        return new WarehouseTransferResource($this->warehouseTransferService->dispatch($warehouseTransfer, auth()->id()));
    }

    public function receive(ReceiveWarehouseTransferRequest $request, WarehouseTransfer $warehouseTransfer): WarehouseTransferResource
    {
        return new WarehouseTransferResource($this->warehouseTransferService->receive($warehouseTransfer, $request->validated(), $request->user()?->id));
    }

    public function cancel(WarehouseTransfer $warehouseTransfer): WarehouseTransferResource
    {
        return new WarehouseTransferResource($this->warehouseTransferService->cancel($warehouseTransfer));
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'sourceWarehouse',
            'destinationWarehouse',
            'createdBy',
            'confirmedBy',
            'dispatchedBy',
            'receivedBy',
            'items.warehouseProduct',
        ];
    }
}
