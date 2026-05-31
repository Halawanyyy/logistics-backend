<?php

namespace App\Modules\Shipment\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\Shipment;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class ShipmentStatusUpdateService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function update(Shipment $shipment, string $status, ?string $notes, ?int $userId, bool $allowCompletionStatuses = false): Shipment
    {
        return DB::transaction(function () use ($shipment, $status, $notes, $userId, $allowCompletionStatuses) {
            $shipment = Shipment::query()
                ->with('order')
                ->lockForUpdate()
                ->findOrFail($shipment->id);

            $oldStatus = (string) $shipment->status;

            if ($oldStatus === $status) {
                return $shipment->fresh();
            }

            $this->ensureStatusCanBeUpdated($shipment, $status, $allowCompletionStatuses);

            $shipment->update([
                'status' => $status,
            ]);

            $shipment->histories()->create([
                'status' => $status,
                'notes' => $notes,
                'changed_by' => $userId,
            ]);

            $this->auditLogService->logStatusChange(
                $shipment->fresh(),
                AuditEventType::SHIPMENT_STATUS_UPDATED,
                $oldStatus,
                $status,
                [
                    'user_id' => $userId,
                ],
                $notes,
            );

            $this->syncOrderStatus($shipment, $status);

            return $shipment->fresh();
        });
    }

    private function ensureStatusCanBeUpdated(Shipment $shipment, string $status, bool $allowCompletionStatuses): void
    {
        if (! $allowCompletionStatuses && in_array($status, [
            ShipmentStatus::DELIVERED,
            ShipmentStatus::PARTIALLY_DELIVERED,
            ShipmentStatus::FAILED,
        ], true)) {
            $this->fail('Use the complete-delivery endpoint to mark a shipment as delivered, partially delivered, or failed.');
        }

        if ($this->isFinalStatus((string) $shipment->status)) {
            $this->fail('Final shipment statuses cannot be changed.');
        }

        $allowedTransitions = $this->allowedTransitions()[(string) $shipment->status] ?? [];

        if (! in_array($status, $allowedTransitions, true)) {
            $this->fail('Invalid shipment status transition.');
        }
    }

    private function syncOrderStatus(Shipment $shipment, string $status): void
    {
        if ($shipment->relationLoaded('order') && $shipment->order !== null) {
            if ($status === ShipmentStatus::CANCELLED) {
                $shipment->order->update(['status' => OrderStatus::CANCELLED]);
                return;
            }

            if (in_array($status, [
                ShipmentStatus::RETURNED,
                ShipmentStatus::RETURNED_TO_HUB,
                ShipmentStatus::RETURNED_TO_WAREHOUSE,
            ], true)) {
                $shipment->order->update(['status' => OrderStatus::FAILED]);
            }
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function allowedTransitions(): array
    {
        return [
            ShipmentStatus::PENDING_PICKUP => [
                ShipmentStatus::DRIVER_ASSIGNED,
                ShipmentStatus::PICKED_UP,
                ShipmentStatus::ARRIVED_AT_HUB,
                ShipmentStatus::SORTING,
                ShipmentStatus::READY_FOR_DISPATCH,
                ShipmentStatus::IN_TRANSIT,
                ShipmentStatus::OUT_FOR_DELIVERY,
                ShipmentStatus::CANCELLED,
            ],
            ShipmentStatus::DRIVER_ASSIGNED => [
                ShipmentStatus::PICKED_UP,
                ShipmentStatus::ARRIVED_AT_HUB,
                ShipmentStatus::SORTING,
                ShipmentStatus::READY_FOR_DISPATCH,
                ShipmentStatus::IN_TRANSIT,
                ShipmentStatus::OUT_FOR_DELIVERY,
                ShipmentStatus::CANCELLED,
            ],
            ShipmentStatus::PICKED_UP => [
                ShipmentStatus::ARRIVED_AT_HUB,
                ShipmentStatus::SORTING,
                ShipmentStatus::READY_FOR_DISPATCH,
                ShipmentStatus::IN_TRANSIT,
                ShipmentStatus::OUT_FOR_DELIVERY,
                ShipmentStatus::CANCELLED,
            ],
            ShipmentStatus::ARRIVED_AT_HUB => [
                ShipmentStatus::SORTING,
                ShipmentStatus::READY_FOR_DISPATCH,
                ShipmentStatus::IN_TRANSIT,
                ShipmentStatus::OUT_FOR_DELIVERY,
                ShipmentStatus::CANCELLED,
            ],
            ShipmentStatus::SORTING => [
                ShipmentStatus::READY_FOR_DISPATCH,
                ShipmentStatus::IN_TRANSIT,
                ShipmentStatus::OUT_FOR_DELIVERY,
                ShipmentStatus::CANCELLED,
            ],
            ShipmentStatus::READY_FOR_DISPATCH => [
                ShipmentStatus::OUT_FOR_DELIVERY,
                ShipmentStatus::IN_TRANSIT,
                ShipmentStatus::CANCELLED,
            ],
            ShipmentStatus::IN_TRANSIT => [
                ShipmentStatus::OUT_FOR_DELIVERY,
                ShipmentStatus::DELIVERY_ATTEMPTED,
                ShipmentStatus::RESCHEDULED,
                ShipmentStatus::RETURNED_TO_HUB,
                ShipmentStatus::RETURNED_TO_WAREHOUSE,
                ShipmentStatus::FAILED,
                ShipmentStatus::CANCELLED,
            ],
            ShipmentStatus::OUT_FOR_DELIVERY => [
                ShipmentStatus::DELIVERY_ATTEMPTED,
                ShipmentStatus::RESCHEDULED,
                ShipmentStatus::DELIVERED,
                ShipmentStatus::PARTIALLY_DELIVERED,
                ShipmentStatus::FAILED,
                ShipmentStatus::RETURNED,
                ShipmentStatus::RETURNED_TO_HUB,
                ShipmentStatus::RETURNED_TO_WAREHOUSE,
                ShipmentStatus::CANCELLED,
            ],
            ShipmentStatus::DELIVERY_ATTEMPTED => [
                ShipmentStatus::OUT_FOR_DELIVERY,
                ShipmentStatus::RESCHEDULED,
                ShipmentStatus::FAILED,
                ShipmentStatus::RETURNED,
                ShipmentStatus::RETURNED_TO_HUB,
                ShipmentStatus::RETURNED_TO_WAREHOUSE,
                ShipmentStatus::CANCELLED,
            ],
            ShipmentStatus::RESCHEDULED => [
                ShipmentStatus::OUT_FOR_DELIVERY,
                ShipmentStatus::DELIVERY_ATTEMPTED,
                ShipmentStatus::FAILED,
                ShipmentStatus::RETURNED,
                ShipmentStatus::RETURNED_TO_HUB,
                ShipmentStatus::RETURNED_TO_WAREHOUSE,
                ShipmentStatus::CANCELLED,
            ],
        ];
    }

    private function isFinalStatus(string $status): bool
    {
        return in_array($status, [
            ShipmentStatus::DELIVERED,
            ShipmentStatus::PARTIALLY_DELIVERED,
            ShipmentStatus::FAILED,
            ShipmentStatus::RETURNED,
            ShipmentStatus::RETURNED_TO_HUB,
            ShipmentStatus::RETURNED_TO_WAREHOUSE,
            ShipmentStatus::CANCELLED,
        ], true);
    }

    private function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
