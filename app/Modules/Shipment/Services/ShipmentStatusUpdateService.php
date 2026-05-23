<?php

namespace App\Modules\Shipment\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Shipment\Models\Shipment;
use Illuminate\Support\Facades\DB;

class ShipmentStatusUpdateService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function update(Shipment $shipment, string $status, ?string $notes, ?int $userId): Shipment
    {
        return DB::transaction(function () use ($shipment, $status, $notes, $userId) {
            $oldStatus = $shipment->status;

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

            return $shipment->fresh();
        });
    }
}
