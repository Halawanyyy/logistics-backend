<?php

namespace App\Modules\Shipment\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Settings\Services\SystemSettingService;
use App\Modules\Shipment\Enums\DeliveryAttemptResult;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\DeliveryAttempt;
use App\Modules\Shipment\Models\Shipment;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class DeliveryAttemptService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly SystemSettingService $systemSettingService,
        private readonly ShipmentStatusUpdateService $shipmentStatusUpdateService,
    ) {
    }

    public function create(Shipment $shipment, array $data, ?int $userId): DeliveryAttempt
    {
        return DB::transaction(function () use ($shipment, $data, $userId) {
            $shipment = Shipment::query()
                ->with(['assignedDriver'])
                ->lockForUpdate()
                ->findOrFail($shipment->id);

            $attemptNumber = ((int) $shipment->deliveryAttempts()->lockForUpdate()->max('attempt_number')) + 1;
            $maxAttempts = max(1, (int) $this->systemSettingService->get('max_delivery_attempts', 3));

            if ($attemptNumber > $maxAttempts) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Maximum delivery attempts reached for this shipment.',
                ], 422));
            }

            $attempt = $shipment->deliveryAttempts()->create([
                'driver_id' => $shipment->assigned_driver_id,
                'attempt_number' => $attemptNumber,
                'attempt_date' => now(),
                'result' => $data['result'],
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'next_action' => $data['next_action'] ?? null,
                'next_attempt_date' => $data['next_attempt_date'] ?? null,
                'created_by' => $userId,
            ]);

            $status = match ($data['result']) {
                DeliveryAttemptResult::FAILED => ShipmentStatus::FAILED,
                DeliveryAttemptResult::POSTPONED, DeliveryAttemptResult::RESCHEDULED => ShipmentStatus::RESCHEDULED,
                default => ShipmentStatus::DELIVERY_ATTEMPTED,
            };

            $notes = $data['notes'] ?? null;
            $this->shipmentStatusUpdateService->update($shipment, $status, $notes, $userId, true);

            $this->auditLogService->log(
                AuditEventType::DELIVERY_ATTEMPT_CREATED,
                'delivery_attempt',
                $shipment->fresh(),
                null,
                [
                    'user_id' => $userId,
                    'new_values' => [
                        'result' => $attempt->result,
                        'reason' => $attempt->reason,
                        'next_action' => $attempt->next_action,
                        'next_attempt_date' => $attempt->next_attempt_date,
                    ],
                    'metadata' => [
                        'attempt_id' => $attempt->id,
                        'attempt_number' => $attempt->attempt_number,
                        'driver_id' => $attempt->driver_id,
                    ],
                    'notes' => $attempt->notes,
                ],
            );

            return $attempt->fresh()->load(['shipment', 'driver', 'createdBy']);
        });
    }
}
