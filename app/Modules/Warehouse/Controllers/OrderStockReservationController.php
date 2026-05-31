<?php

namespace App\Modules\Warehouse\Controllers;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Resources\OrderResource;
use App\Modules\Warehouse\Models\OrderStockReservation;
use App\Modules\Warehouse\Requests\ListOrderStockReservationsRequest;
use App\Modules\Warehouse\Requests\ReserveStockRequest;
use App\Modules\Warehouse\Resources\OrderStockReservationResource;
use App\Modules\Warehouse\Services\WarehouseOrderReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderStockReservationController
{
    public function __construct(
        private readonly WarehouseOrderReservationService $warehouseOrderReservationService,
    ) {
    }

    public function reserve(ReserveStockRequest $request, Order $order): JsonResponse
    {
        $this->warehouseOrderReservationService->reserve($order, $request->validated());

        return (new OrderResource($order->fresh()->load($this->orderRelations())))
            ->response()
            ->setStatusCode(201);
    }

    public function release(Order $order): AnonymousResourceCollection
    {
        $reservations = $this->warehouseOrderReservationService->release($order);

        return OrderStockReservationResource::collection($reservations);
    }

    public function fulfill(Order $order): AnonymousResourceCollection
    {
        $reservations = $this->warehouseOrderReservationService->fulfill($order);

        return OrderStockReservationResource::collection($reservations);
    }

    public function orderReservations(Order $order): AnonymousResourceCollection
    {
        $reservations = $order->stockReservations()
            ->with($this->reservationRelations())
            ->latest()
            ->get();

        return OrderStockReservationResource::collection($reservations);
    }

    public function index(ListOrderStockReservationsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $reservations = OrderStockReservation::query()
            ->with($this->reservationRelations())
            ->when(isset($validated['order_id']), fn ($query) => $query->where('order_id', $validated['order_id']))
            ->when(isset($validated['warehouse_id']), fn ($query) => $query->where('warehouse_id', $validated['warehouse_id']))
            ->when(isset($validated['warehouse_product_id']), fn ($query) => $query->where('warehouse_product_id', $validated['warehouse_product_id']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['merchant_id']), fn ($query) => $query->whereHas('warehouseProduct', fn ($inner) => $inner->where('merchant_id', $validated['merchant_id'])))
            ->latest()
            ->get();

        return OrderStockReservationResource::collection($reservations);
    }

    /**
     * @return list<string>
     */
    private function orderRelations(): array
    {
        return [
            'merchant',
            'deliveryGovernorate',
            'deliveryArea',
            'items',
            'latestStockReservation',
            'stockReservations.warehouse',
            'stockReservations.warehouseProduct.merchant',
            'stockReservations.createdBy',
        ];
    }

    /**
     * @return list<string>
     */
    private function reservationRelations(): array
    {
        return [
            'warehouse',
            'warehouseProduct.merchant',
            'createdBy',
        ];
    }
}
