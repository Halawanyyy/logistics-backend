<?php

namespace App\Modules\Shipment\Controllers;

use App\Modules\Shipment\Models\DeliveryAttempt;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Shipment\Requests\ListDeliveryAttemptsRequest;
use App\Modules\Shipment\Requests\StoreDeliveryAttemptRequest;
use App\Modules\Shipment\Resources\DeliveryAttemptResource;
use App\Modules\Shipment\Services\DeliveryAttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryAttemptController
{
    public function __construct(
        private readonly DeliveryAttemptService $deliveryAttemptService,
    ) {
    }

    public function index(ListDeliveryAttemptsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $attempts = DeliveryAttempt::query()
            ->with(['shipment', 'driver', 'createdBy'])
            ->when(isset($validated['shipment_id']), fn ($query) => $query->where('shipment_id', $validated['shipment_id']))
            ->when(isset($validated['driver_id']), fn ($query) => $query->where('driver_id', $validated['driver_id']))
            ->when(isset($validated['result']), fn ($query) => $query->where('result', $validated['result']))
            ->when(isset($validated['reason']), fn ($query) => $query->where('reason', $validated['reason']))
            ->when(isset($validated['date_from']), fn ($query) => $query->whereDate('attempt_date', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn ($query) => $query->whereDate('attempt_date', '<=', $validated['date_to']))
            ->latest('attempt_date')
            ->latest('id')
            ->get();

        return DeliveryAttemptResource::collection($attempts);
    }

    public function store(StoreDeliveryAttemptRequest $request, Shipment $shipment): JsonResponse
    {
        $attempt = $this->deliveryAttemptService->create($shipment, $request->validated(), $request->user()?->id);

        return (new DeliveryAttemptResource($attempt))
            ->response()
            ->setStatusCode(201);
    }
}
