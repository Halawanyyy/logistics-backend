<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Warehouse\Enums\PackingTaskStatus;
use App\Modules\Warehouse\Enums\WarehouseChargeType;
use App\Modules\Warehouse\Models\PackingTask;
use App\Modules\Warehouse\Models\PickingList;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PackingTaskService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly WarehouseChargeService $warehouseChargeService,
    ) {
    }

    /**
     * @return Collection<int, PackingTask>
     */
    public function generateFromPickingList(PickingList $pickingList): Collection
    {
        return DB::transaction(function () use ($pickingList) {
            $pickingList = PickingList::query()
                ->with(['items.order.shipment'])
                ->lockForUpdate()
                ->findOrFail($pickingList->id);

            $tasks = collect();

            foreach ($pickingList->items->groupBy('order_id') as $orderId => $items) {
                $existingTask = PackingTask::query()
                    ->where('picking_list_id', $pickingList->id)
                    ->where('order_id', $orderId)
                    ->first();

                if ($existingTask !== null) {
                    $tasks->push($existingTask);
                    continue;
                }

                $order = $items->first()?->order;

                if ($order === null) {
                    continue;
                }

                $tasks->push(PackingTask::create([
                    'packing_number' => $this->generatePackingNumber(),
                    'picking_list_id' => $pickingList->id,
                    'order_id' => $order->id,
                    'shipment_id' => $order->shipment?->id,
                    'status' => PackingTaskStatus::PENDING,
                ]));
            }

            return $tasks->load([
                'pickingList',
                'order',
                'shipment',
                'packedBy',
            ]);
        });
    }

    public function complete(PackingTask $packingTask, array $data, ?int $userId): PackingTask
    {
        return DB::transaction(function () use ($packingTask, $data, $userId) {
            $packingTask = PackingTask::query()
                ->with(['order', 'pickingList'])
                ->lockForUpdate()
                ->findOrFail($packingTask->id);

            if ($packingTask->status === PackingTaskStatus::PACKED) {
                $this->fail('Packing task is already packed.');
            }

            if ($packingTask->status === PackingTaskStatus::CANCELLED) {
                $this->fail('Cancelled packing task cannot be completed.');
            }

            $packingTask->update([
                'status' => PackingTaskStatus::PACKED,
                'package_weight' => $data['package_weight'] ?? null,
                'package_length' => $data['package_length'] ?? null,
                'package_width' => $data['package_width'] ?? null,
                'package_height' => $data['package_height'] ?? null,
                'packaging_fee' => $data['packaging_fee'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'packed_by' => $userId,
                'packed_at' => now(),
            ]);

            if ((float) ($data['packaging_fee'] ?? 0) > 0) {
                $this->warehouseChargeService->store([
                    'merchant_id' => $packingTask->order->merchant_id,
                    'warehouse_id' => $packingTask->pickingList?->warehouse_id,
                    'order_id' => $packingTask->order_id,
                    'shipment_id' => $packingTask->shipment_id,
                    'type' => WarehouseChargeType::PACKAGING,
                    'description' => 'Packing task fee',
                    'quantity' => 1,
                    'unit_price' => $data['packaging_fee'],
                    'notes' => $data['notes'] ?? null,
                ], $userId);
            }

            $this->auditLogService->log(
                AuditEventType::PACKING_COMPLETED,
                'completed',
                $packingTask->fresh(),
                null,
                [
                    'user_id' => $userId,
                    'new_values' => [
                        'status' => PackingTaskStatus::PACKED,
                        'packaging_fee' => $packingTask->packaging_fee,
                    ],
                    'notes' => $packingTask->notes ?? 'Packing task completed.',
                ],
            );

            return $packingTask->fresh()->load([
                'pickingList',
                'order',
                'shipment',
                'packedBy',
            ]);
        });
    }

    private function generatePackingNumber(): string
    {
        $datePart = now()->format('Ymd');
        $prefix = 'PACK-'.$datePart.'-';

        $latest = PackingTask::withTrashed()
            ->where('packing_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('packing_number')
            ->value('packing_number');

        $nextSequence = $latest === null ? 1 : ((int) substr((string) $latest, -6)) + 1;

        return $prefix.str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }

    private function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
