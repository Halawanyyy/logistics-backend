<?php

namespace App\Modules\Driver\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Driver\Enums\DriverManifestStatus;
use App\Modules\Driver\Models\DriverManifest;
use App\Modules\Driver\Models\DriverManifestItem;
use App\Modules\Finance\Enums\DriverCashClosureStatus;
use App\Modules\Finance\Models\DriverCashClosure;
use App\Modules\Order\Enums\PaymentType;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Shipment\Services\ShipmentStatusUpdateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class DriverManifestService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ShipmentStatusUpdateService $shipmentStatusUpdateService,
    ) {}

    public function generate(array $data, ?int $userId): DriverManifest
    {
        return DB::transaction(function () use ($data, $userId) {
            $existingOpenManifest = DriverManifest::query()
                ->where('driver_id', $data['driver_id'])
                ->whereDate('manifest_date', $data['manifest_date'])
                ->where('status', DriverManifestStatus::OPEN)
                ->lockForUpdate()
                ->exists();

            if ($existingOpenManifest) {
                $this->fail('An open manifest already exists for this driver on the selected date.');
            }

            $shipments = Shipment::query()
                ->whereIn('id', $data['shipment_ids'])
                ->with([
                    'order:id,order_number,payment_type',
                    'order.items:id,order_id,delivered_quantity,unit_price',
                    'merchant:id,name',
                ])
                ->lockForUpdate()
                ->get();

            if ($shipments->count() !== count($data['shipment_ids'])) {
                $this->fail('One or more shipments were not found.');
            }

            foreach ($shipments as $shipment) {
                if (! in_array($shipment->status, [ShipmentStatus::READY_FOR_DISPATCH, ShipmentStatus::OUT_FOR_DELIVERY], true)) {
                    $this->fail('Selected shipments must be ready for dispatch or already out for delivery.');
                }

                if ($shipment->assigned_driver_id !== null && (int) $shipment->assigned_driver_id !== (int) $data['driver_id']) {
                    $this->fail('Shipment is already assigned to another driver.');
                }

                $isInAnotherOpenManifest = DriverManifestItem::query()
                    ->where('shipment_id', $shipment->id)
                    ->whereHas('manifest', fn (Builder $query) => $query->where('status', DriverManifestStatus::OPEN))
                    ->exists();

                if ($isInAnotherOpenManifest) {
                    $this->fail('A shipment cannot belong to multiple open manifests.');
                }
            }

            $manifest = DriverManifest::create([
                'manifest_number' => $this->generateManifestNumber(),
                'driver_id' => $data['driver_id'],
                'manifest_date' => $data['manifest_date'],
                'status' => DriverManifestStatus::OPEN,
                'total_shipments' => $shipments->count(),
                'expected_cod_amount' => number_format($shipments->sum(fn (Shipment $shipment): float => $this->shipmentExpectedCodAtLoadout($shipment)), 2, '.', ''),
                'difference_amount' => 0,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($shipments as $shipment) {
                if ((int) $shipment->assigned_driver_id !== (int) $data['driver_id']) {
                    $shipment->update([
                        'assigned_driver_id' => $data['driver_id'],
                    ]);
                }

                $manifest->items()->create([
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_number,
                    'customer_name' => $shipment->customer_name,
                    'customer_phone' => $shipment->customer_phone,
                    'delivery_address' => $shipment->delivery_address,
                    'cod_amount' => number_format($this->shipmentExpectedCodAtLoadout($shipment), 2, '.', ''),
                    'shipping_fee' => $shipment->shipping_fee,
                    'shipment_status_at_loadout' => $shipment->status,
                    'notes' => $shipment->tracking_notes,
                ]);

                $this->shipmentStatusUpdateService->update(
                    $shipment,
                    ShipmentStatus::OUT_FOR_DELIVERY,
                    'Added to driver manifest '.$manifest->manifest_number.'.',
                    $userId
                );
            }

            $this->auditLogService->log(
                AuditEventType::MANIFEST_GENERATED,
                'generated',
                $manifest,
                null,
                [
                    'user_id' => $userId,
                    'new_values' => [
                        'status' => $manifest->status,
                        'total_shipments' => $manifest->total_shipments,
                        'expected_cod_amount' => $manifest->expected_cod_amount,
                    ],
                    'notes' => $manifest->notes,
                ],
            );

            return $this->loadManifest($manifest);
        });
    }

    public function close(DriverManifest $manifest, array $data, ?int $userId): DriverManifest
    {
        return DB::transaction(function () use ($manifest, $data, $userId) {
            $manifest = DriverManifest::query()
                ->with([
                    'driver',
                    'items.shipment.order:id,payment_type,order_number',
                    'items.shipment.order.items:id,order_id,delivered_quantity,unit_price',
                    'items.shipment.merchant:id,name',
                    'cashClosure',
                ])
                ->lockForUpdate()
                ->findOrFail($manifest->id);

            if ($manifest->status !== DriverManifestStatus::OPEN) {
                $this->fail('Only open manifests can be closed.');
            }

            $expectedAmount = 0.0;

            foreach ($manifest->items as $item) {
                $shipment = $item->shipment;

                if ($shipment === null) {
                    $this->fail('Manifest contains a missing shipment reference.');
                }

                if ($shipment->status !== ShipmentStatus::DELIVERED) {
                    $this->fail(
                        'Cannot close manifest until all shipments are delivered. '
                        .$shipment->shipment_number.' is currently '.$shipment->status.'.'
                    );
                }

                $deliveredCodAmount = $this->shipmentExpectedCodAtClose($shipment);
                $expectedAmount += $deliveredCodAmount;

                $item->update([
                    'final_status' => $shipment->status,
                    'delivered_cod_amount' => number_format($deliveredCodAmount, 2, '.', ''),
                ]);
            }

            $actualReceivedAmount = (float) $data['actual_received_amount'];
            $differenceAmount = $actualReceivedAmount - $expectedAmount;

            $manifest->update([
                'status' => DriverManifestStatus::CLOSED,
                'expected_cod_amount' => number_format($expectedAmount, 2, '.', ''),
                'actual_received_amount' => number_format($actualReceivedAmount, 2, '.', ''),
                'difference_amount' => number_format($differenceAmount, 2, '.', ''),
                'notes' => $data['notes'] ?? $manifest->notes,
                'closed_by' => $userId,
                'closed_at' => now(),
            ]);

            $cashClosure = DriverCashClosure::updateOrCreate(
                ['driver_manifest_id' => $manifest->id],
                [
                    'driver_id' => $manifest->driver_id,
                    'closure_date' => $manifest->manifest_date,
                    'expected_amount' => number_format($expectedAmount, 2, '.', ''),
                    'received_amount' => number_format($actualReceivedAmount, 2, '.', ''),
                    'difference_amount' => number_format($differenceAmount, 2, '.', ''),
                    'status' => DriverCashClosureStatus::PENDING,
                    'notes' => $data['notes'] ?? $manifest->notes,
                    'created_by' => $manifest->cashClosure?->created_by ?? $userId,
                    'verified_by' => null,
                    'verified_at' => null,
                ]
            );

            $this->auditLogService->log(
                AuditEventType::MANIFEST_CLOSED,
                'closed',
                $manifest->fresh(),
                null,
                [
                    'user_id' => $userId,
                    'new_values' => [
                        'status' => DriverManifestStatus::CLOSED,
                        'actual_received_amount' => number_format($actualReceivedAmount, 2, '.', ''),
                        'difference_amount' => number_format($differenceAmount, 2, '.', ''),
                    ],
                    'metadata' => [
                        'driver_cash_closure_id' => $cashClosure->id,
                    ],
                    'notes' => $data['notes'] ?? $manifest->notes,
                ],
            );

            return $this->loadManifest($manifest);
        });
    }

    public function loadManifest(DriverManifest $manifest): DriverManifest
    {
        return $manifest->fresh()->load([
            'driver',
            'createdBy',
            'closedBy',
            'cashClosure.driver',
            'cashClosure.treasuryAccount',
            'cashClosure.createdBy',
            'cashClosure.verifiedBy',
            'items.shipment.order',
            'items.shipment.merchant',
        ]);
    }

    /**
     * @return list<string>
     */
    public function closableShipmentStatuses(): array
    {
        return [
            ShipmentStatus::DELIVERED,
        ];
    }

    private function generateManifestNumber(): string
    {
        $datePart = now()->format('Ymd');
        $prefix = 'DM-'.$datePart.'-';

        $latest = DriverManifest::withTrashed()
            ->where('manifest_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('manifest_number')
            ->value('manifest_number');

        $nextSequence = $latest === null ? 1 : ((int) substr((string) $latest, -6)) + 1;

        return $prefix.str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }

    private function shipmentExpectedCodAtLoadout(Shipment $shipment): float
    {
        if ($shipment->order?->payment_type !== PaymentType::COD) {
            return 0;
        }

        $deliveredCodAmount = (float) $shipment->delivered_cod_amount;

        if ($deliveredCodAmount > 0) {
            return $deliveredCodAmount;
        }

        return (float) $shipment->cod_amount;
    }

    private function shipmentExpectedCodAtClose(Shipment $shipment): float
    {
        if ($shipment->order?->payment_type !== PaymentType::COD) {
            return 0;
        }

        $deliveredCodAmount = (float) $shipment->delivered_cod_amount;

        if ($shipment->status === ShipmentStatus::PARTIALLY_DELIVERED) {
            return $deliveredCodAmount;
        }

        if (in_array($shipment->status, ShipmentStatus::financiallyCompletedValues(), true)) {
            return $deliveredCodAmount > 0 ? $deliveredCodAmount : (float) $shipment->cod_amount;
        }

        return 0;
    }

    private function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
