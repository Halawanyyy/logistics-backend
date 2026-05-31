<?php

namespace App\Modules\Warehouse\Controllers;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Resources\OrderResource;
use App\Modules\Warehouse\Requests\MarkOrderPackedRequest;
use App\Modules\Warehouse\Requests\StartOrderWarehousePickingRequest;
use App\Modules\Warehouse\Services\OrderWarehouseWorkflowService;
use Illuminate\Http\Request;

class OrderWarehouseWorkflowController
{
    public function __construct(
        private readonly OrderWarehouseWorkflowService $orderWarehouseWorkflowService,
    ) {
    }

    public function startPicking(StartOrderWarehousePickingRequest $request, Order $order): OrderResource
    {
        return new OrderResource($this->orderWarehouseWorkflowService->startPicking($order, $request->validated(), $request->user()?->id)->load($this->relations()));
    }

    public function markPicked(Order $order): OrderResource
    {
        return new OrderResource($this->orderWarehouseWorkflowService->markPicked($order)->load($this->relations()));
    }

    public function startPacking(Request $request, Order $order): OrderResource
    {
        return new OrderResource($this->orderWarehouseWorkflowService->startPacking($order, $request->user()?->id)->load($this->relations()));
    }

    public function markPacked(MarkOrderPackedRequest $request, Order $order): OrderResource
    {
        return new OrderResource($this->orderWarehouseWorkflowService->markPacked($order, $request->validated(), $request->user()?->id)->load($this->relations()));
    }

    public function readyForDispatch(Order $order): OrderResource
    {
        return new OrderResource($this->orderWarehouseWorkflowService->readyForDispatch($order)->load($this->relations()));
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'merchant',
            'deliveryGovernorate',
            'deliveryArea',
            'items',
            'stockReservations.warehouse',
            'stockReservations.warehouseProduct.merchant',
            'stockReservations.createdBy',
        ];
    }
}
