<?php

namespace App\Modules\Shipment\Actions;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Driver\Enums\DriverStatus;
use App\Modules\Driver\Models\Driver;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\Shipment;
use Illuminate\Http\Exceptions\HttpResponseException;

class AssignDriverToShipmentAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function execute(Shipment $shipment, Driver $driver): Shipment
    {
        if ($driver->status !== DriverStatus::ACTIVE) {
            $this->fail('Selected driver is inactive.');
        }

        if ($shipment->status === ShipmentStatus::DELIVERED) {
            $this->fail('Delivered shipment cannot be reassigned.');
        }

        if (in_array($shipment->status, [
            ShipmentStatus::RETURNED,
            ShipmentStatus::RETURNED_TO_HUB,
            ShipmentStatus::RETURNED_TO_WAREHOUSE,
        ], true)) {
            $this->fail('Completed return shipment cannot be reassigned.');
        }

        if ($shipment->status === ShipmentStatus::CANCELLED) {
            $this->fail('Cancelled shipment cannot be assigned.');
        }

        $oldDriverId = $shipment->assigned_driver_id;

        $shipment->update([
            'assigned_driver_id' => $driver->id,
        ]);

        $this->auditLogService->log(
            AuditEventType::SHIPMENT_DRIVER_ASSIGNED,
            'assigned_driver',
            $shipment->fresh(),
            null,
            [
                'old_values' => ['assigned_driver_id' => $oldDriverId],
                'new_values' => ['assigned_driver_id' => $driver->id],
                'metadata' => [
                    'driver_name' => $driver->name,
                ],
                'notes' => 'Driver assigned to shipment.',
            ],
        );

        return $shipment->fresh();
    }

    private function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
