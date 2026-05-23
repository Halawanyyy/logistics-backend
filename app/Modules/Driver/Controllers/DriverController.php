<?php

namespace App\Modules\Driver\Controllers;

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Requests\StoreDriverRequest;
use App\Modules\Driver\Requests\UpdateDriverRequest;
use App\Modules\Driver\Resources\DriverManifestResource;
use App\Modules\Driver\Resources\DriverResource;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Resources\ShipmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class DriverController
{
    public function index(): AnonymousResourceCollection
    {
        $drivers = Driver::query()->latest()->get();

        return DriverResource::collection($drivers);
    }

    public function store(StoreDriverRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['status'] = $validated['status'] ?? 'active';

        $driver = Driver::create($validated);

        return (new DriverResource($driver))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Driver $driver): DriverResource
    {
        return new DriverResource($driver);
    }

    public function update(UpdateDriverRequest $request, Driver $driver): DriverResource
    {
        $driver->update($request->validated());

        return new DriverResource($driver->fresh());
    }

    public function destroy(Driver $driver): JsonResponse
    {
        $driver->delete();

        return response()->json([
            'message' => 'Driver deleted successfully',
        ]);
    }

    public function shipments(Driver $driver): AnonymousResourceCollection
    {
        $shipments = $driver->shipments()
            ->with([
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
            ])
            ->latest()
            ->get();

        return ShipmentResource::collection($shipments);
    }

    public function manifest(Request $request, Driver $driver): DriverManifestResource
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(ShipmentStatus::values())],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $shipments = $driver->shipments()
            ->with([
                'merchant',
                'deliveryGovernorate',
                'deliveryArea',
                'order',
                'order.items',
                'itemResults',
                'itemResults.orderItem',
            ])
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['date']), fn ($query) => $query->whereDate('created_at', $validated['date']))
            ->orderBy('created_at')
            ->get();

        return new DriverManifestResource([
            'driver' => $driver,
            'generated_at' => now()->toISOString(),
            'shipments' => $shipments,
        ]);
    }
}
