<?php

namespace App\Modules\Shipment\Controllers;

use App\Modules\Driver\Models\Driver;
use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Actions\AssignDriverToShipmentAction;
use App\Modules\Shipment\Actions\CreateShipmentFromOrderAction;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Shipment\Requests\AssignDriverRequest;
use App\Modules\Shipment\Requests\CompleteShipmentDeliveryRequest;
use App\Modules\Shipment\Requests\ListShipmentsRequest;
use App\Modules\Shipment\Requests\PrintShipmentListRequest;
use App\Modules\Shipment\Requests\UpdateShipmentStatusRequest;
use App\Modules\Shipment\Resources\ShipmentResource;
use App\Modules\Shipment\Services\ShipmentDeliveryCompletionService;
use App\Modules\Shipment\Services\ShipmentPrintListService;
use App\Modules\Shipment\Services\ShipmentStatusUpdateService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ShipmentController
{
    public function __construct(
        private readonly AssignDriverToShipmentAction $assignDriverToShipmentAction,
        private readonly CreateShipmentFromOrderAction $createShipmentFromOrderAction,
        private readonly ShipmentDeliveryCompletionService $shipmentDeliveryCompletionService,
        private readonly ShipmentPrintListService $shipmentPrintListService,
        private readonly ShipmentStatusUpdateService $shipmentStatusUpdateService,
    ) {
    }

    public function index(ListShipmentsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $shipments = Shipment::query()
            ->with($this->relations())
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(($validated['partially_delivered'] ?? false) === true, fn ($query) => $query->where('status', \App\Modules\Shipment\Enums\ShipmentStatus::PARTIALLY_DELIVERED))
            ->when(isset($validated['merchant_id']), fn ($query) => $query->where('merchant_id', $validated['merchant_id']))
            ->when(isset($validated['order_type']), fn ($query) => $query->whereHas('order', fn ($orderQuery) => $orderQuery->where('order_type', $validated['order_type'])))
            ->when(isset($validated['payment_type']), fn ($query) => $query->whereHas('order', fn ($orderQuery) => $orderQuery->where('payment_type', $validated['payment_type'])))
            ->when(($validated['cod_only'] ?? false) === true, fn ($query) => $query->where('cod_amount', '>', 0))
            ->when(isset($validated['assigned_driver_id']), fn ($query) => $query->where('assigned_driver_id', $validated['assigned_driver_id']))
            ->when(isset($validated['delivery_governorate_id']), fn ($query) => $query->where('delivery_governorate_id', $validated['delivery_governorate_id']))
            ->when(isset($validated['delivery_area_id']), fn ($query) => $query->where('delivery_area_id', $validated['delivery_area_id']))
            ->when(isset($validated['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $validated['date_to']))
            ->when(isset($validated['shipment_number']), fn ($query) => $query->where('shipment_number', 'like', '%'.$validated['shipment_number'].'%'))
            ->when(isset($validated['search']), function ($query) use ($validated) {
                $search = $validated['search'];

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('shipment_number', 'like', '%'.$search.'%')
                        ->orWhere('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('customer_phone', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->get();

        return ShipmentResource::collection($shipments);
    }

    public function show(Shipment $shipment): ShipmentResource
    {
        return new ShipmentResource($shipment->load($this->relations()));
    }

    public function completeDelivery(CompleteShipmentDeliveryRequest $request, Shipment $shipment): ShipmentResource
    {
        $shipment = $this->shipmentDeliveryCompletionService->execute(
            $shipment,
            $request->validated(),
            $request->user()?->id,
        );

        return new ShipmentResource($shipment);
    }

    public function printList(PrintShipmentListRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $shipments = $this->shipmentPrintListService->query($validated)->get();

        return response()->json([
            'data' => [
                'generated_at' => now()->toISOString(),
                'filters' => $validated,
                'summary' => [
                    'total_shipments' => $shipments->count(),
                    'total_cod_amount' => number_format((float) $shipments->sum('cod_amount'), 2, '.', ''),
                    'total_shipping_fee' => number_format((float) $shipments->sum('shipping_fee'), 2, '.', ''),
                ],
                'shipments' => $shipments->map(fn (Shipment $shipment) => [
                    'shipment_number' => $shipment->shipment_number,
                    'customer_name' => $shipment->customer_name,
                    'customer_phone' => $shipment->customer_phone,
                    'delivery_address' => $shipment->delivery_address,
                    'delivery_governorate_name' => $shipment->deliveryGovernorate?->name,
                    'delivery_area_name' => $shipment->deliveryArea?->name,
                    'merchant_name' => $shipment->merchant?->name,
                    'merchant_phone' => $shipment->merchant?->phone,
                    'driver_name' => $shipment->assignedDriver?->name,
                    'cod_amount' => $shipment->cod_amount,
                ])->values(),
            ],
        ]);
    }

    public function createFromOrder(Request $request, Order $order): ShipmentResource
    {
        $shipment = $this->createShipmentFromOrderAction->execute($order, $request->user()?->id);

        return new ShipmentResource($shipment->load($this->relations()));
    }

    public function updateStatus(UpdateShipmentStatusRequest $request, Shipment $shipment): ShipmentResource
    {
        $shipment = $this->shipmentStatusUpdateService->update(
            $shipment,
            $request->string('status')->toString(),
            $request->input('notes'),
            $request->user()?->id,
        );

        return new ShipmentResource($shipment->load($this->relations()));
    }

    public function assignDriver(AssignDriverRequest $request, Shipment $shipment): ShipmentResource
    {
        $driver = Driver::query()->findOrFail($request->integer('driver_id'));
        $shipment = $this->assignDriverToShipmentAction->execute($shipment, $driver);

        return new ShipmentResource($shipment->load($this->relations()));
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'merchant',
            'order',
            'order.items',
            'deliveryGovernorate',
            'deliveryArea',
            'assignedDriver',
            'histories',
            'histories.changedBy',
            'itemResults',
            'itemResults.orderItem',
            'waybill',
        ];
    }
}
