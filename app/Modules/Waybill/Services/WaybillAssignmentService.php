<?php

namespace App\Modules\Waybill\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Waybill\Enums\WaybillSource;
use App\Modules\Waybill\Enums\WaybillStatus;
use App\Modules\Waybill\Models\Waybill;
use Illuminate\Http\Exceptions\HttpResponseException;

class WaybillAssignmentService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly WaybillNumberGenerator $numberGenerator,
    ) {}

    public function assignPreprinted(Order $order, string $numberOrBarcode, ?int $userId): Waybill
    {
        $waybill = Waybill::query()
            ->where(function ($query) use ($numberOrBarcode): void {
                $query->where('waybill_number', $numberOrBarcode)
                    ->orWhere('barcode', $numberOrBarcode);
            })
            ->lockForUpdate()
            ->first();

        if ($waybill === null) {
            $this->fail('WAYBILL_NOT_FOUND', 'Waybill not found.', 404);
        }

        if ($waybill->source !== WaybillSource::PREPRINTED) {
            $this->fail('INVALID_WAYBILL_SOURCE', 'Only pre-printed waybills can be assigned by number.', 422);
        }

        if ($waybill->status === WaybillStatus::VOID) {
            $this->fail('WAYBILL_VOID', 'This waybill has been voided and cannot be assigned.', 409);
        }

        if ($waybill->status === WaybillStatus::ASSIGNED || $waybill->order_id !== null) {
            $this->fail('WAYBILL_ALREADY_ASSIGNED', 'This waybill has already been assigned to another order.', 409);
        }

        $assignedAt = now();
        $updated = Waybill::query()
            ->whereKey($waybill->id)
            ->where('status', WaybillStatus::AVAILABLE)
            ->whereNull('order_id')
            ->update([
                'status' => WaybillStatus::ASSIGNED,
                'merchant_id' => $order->merchant_id,
                'order_id' => $order->id,
                'assigned_at' => $assignedAt,
                'updated_at' => $assignedAt,
            ]);

        if ($updated !== 1) {
            $this->fail('WAYBILL_ALREADY_ASSIGNED', 'This waybill has already been assigned to another order.', 409);
        }

        $waybill = $waybill->fresh();
        $this->logAssignment($waybill, $order, null, $userId);

        return $waybill;
    }

    public function linkOrCreateForShipment(Order $order, Shipment $shipment, ?int $userId): Waybill
    {
        $existing = Waybill::query()
            ->where('order_id', $order->id)
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            if ($existing->status !== WaybillStatus::ASSIGNED || $existing->shipment_id !== null) {
                $this->fail('WAYBILL_NOT_AVAILABLE', 'The order waybill cannot be linked to this shipment.', 409);
            }

            $existing->update(['shipment_id' => $shipment->id]);

            return $existing->fresh();
        }

        $number = $this->numberGenerator->next();
        $generatedAt = now();
        $waybill = Waybill::query()->create([
            'waybill_number' => $number,
            'barcode' => $number,
            'status' => WaybillStatus::ASSIGNED,
            'source' => WaybillSource::AUTOMATIC,
            'merchant_id' => $order->merchant_id,
            'order_id' => $order->id,
            'shipment_id' => $shipment->id,
            'generated_by' => $userId,
            'generated_at' => $generatedAt,
            'assigned_at' => $generatedAt,
        ]);

        $this->logAssignment($waybill, $order, $shipment, $userId);

        return $waybill;
    }

    private function logAssignment(Waybill $waybill, Order $order, ?Shipment $shipment, ?int $userId): void
    {
        $this->auditLogService->log(
            AuditEventType::WAYBILL_ASSIGNED,
            'assigned',
            'waybill',
            $waybill->id,
            [
                'user_id' => $userId,
                'metadata' => [
                    'waybill_id' => $waybill->id,
                    'waybill_number' => $waybill->waybill_number,
                    'source' => $waybill->source,
                    'order_id' => $order->id,
                    'shipment_id' => $shipment?->id,
                    'assigned_at' => $waybill->assigned_at?->toISOString(),
                ],
                'notes' => 'Waybill assigned to order.',
            ],
        );
    }

    private function fail(string $code, string $message, int $status): never
    {
        throw new HttpResponseException(response()->json([
            'code' => $code,
            'message' => $message,
        ], $status));
    }
}
