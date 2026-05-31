<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Order\Models\Order;
use App\Modules\Warehouse\Enums\OrderStockReservationStatus;
use App\Modules\Warehouse\Enums\PackingTaskStatus;
use App\Modules\Warehouse\Enums\PickingListItemStatus;
use App\Modules\Warehouse\Enums\PickingListStatus;
use App\Modules\Warehouse\Enums\WarehouseFulfillmentStatus;
use App\Modules\Warehouse\Models\PackingTask;
use App\Modules\Warehouse\Models\PickingList;
use App\Modules\Warehouse\Models\PickingListItem;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class OrderWarehouseWorkflowService
{
    public function __construct(
        private readonly OrderWarehouseStatusService $orderWarehouseStatusService,
        private readonly PickingListService $pickingListService,
        private readonly PackingTaskService $packingTaskService,
    ) {
    }

    public function startPicking(Order $order, array $data, ?int $userId): Order
    {
        return DB::transaction(function () use ($order, $data, $userId) {
            $order = Order::query()
                ->with(['stockReservations'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->orderWarehouseStatusService->ensureWarehouseOrder($order);
            $this->orderWarehouseStatusService->ensureCurrentStatus(
                $order,
                [WarehouseFulfillmentStatus::RESERVED, WarehouseFulfillmentStatus::PICKING],
                'Order must have reserved stock before picking can start.',
            );

            $reservations = $order->stockReservations->where('status', OrderStockReservationStatus::RESERVED);

            if ($reservations->isEmpty()) {
                $this->fail('Cannot start picking without an active stock reservation.');
            }

            $warehouseId = (int) $reservations->first()->warehouse_id;
            $existingList = PickingList::query()
                ->whereHas('items', fn ($query) => $query->where('order_id', $order->id))
                ->where('warehouse_id', $warehouseId)
                ->where('status', '!=', PickingListStatus::CANCELLED)
                ->latest()
                ->first();

            if ($existingList === null) {
                $this->pickingListService->generate([
                    'warehouse_id' => $warehouseId,
                    'order_ids' => [$order->id],
                    'assigned_to' => $data['assigned_to'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ], $userId);
            }

            return $this->orderWarehouseStatusService->setStatus($order, WarehouseFulfillmentStatus::PICKING);
        });
    }

    public function markPicked(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->orderWarehouseStatusService->ensureWarehouseOrder($order);
            $this->orderWarehouseStatusService->ensureCurrentStatus(
                $order,
                [WarehouseFulfillmentStatus::PICKING, WarehouseFulfillmentStatus::RESERVED],
                'Picking must be started before it can be marked as picked.',
            );

            $items = PickingListItem::query()
                ->where('order_id', $order->id)
                ->whereIn('status', [PickingListItemStatus::PENDING, PickingListItemStatus::PARTIALLY_PICKED])
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                $this->fail('No picking items are available for this order.');
            }

            foreach ($items as $item) {
                $item->update([
                    'quantity_picked' => $item->quantity_required,
                    'status' => PickingListItemStatus::PICKED,
                ]);
            }

            $pickingListIds = $items->pluck('picking_list_id')->unique()->values();

            foreach ($pickingListIds as $pickingListId) {
                $remaining = PickingListItem::query()
                    ->where('picking_list_id', $pickingListId)
                    ->where('status', '!=', PickingListItemStatus::PICKED)
                    ->exists();

                PickingList::query()
                    ->whereKey($pickingListId)
                    ->update([
                        'status' => $remaining ? PickingListStatus::IN_PROGRESS : PickingListStatus::COMPLETED,
                        'picked_at' => $remaining ? null : now(),
                    ]);
            }

            return $this->orderWarehouseStatusService->setStatus($order, WarehouseFulfillmentStatus::PICKED);
        });
    }

    public function startPacking(Order $order, ?int $userId): Order
    {
        return DB::transaction(function () use ($order, $userId) {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->orderWarehouseStatusService->ensureWarehouseOrder($order);
            $this->orderWarehouseStatusService->ensureCurrentStatus(
                $order,
                [WarehouseFulfillmentStatus::PICKED, WarehouseFulfillmentStatus::PACKING],
                'Order must be picked before packing can start.',
            );

            $pickingList = PickingList::query()
                ->whereHas('items', fn ($query) => $query->where('order_id', $order->id))
                ->where('status', PickingListStatus::COMPLETED)
                ->latest()
                ->first();

            if ($pickingList === null) {
                $this->fail('No completed picking list was found for this order.');
            }

            $task = PackingTask::query()
                ->where('order_id', $order->id)
                ->where('picking_list_id', $pickingList->id)
                ->latest()
                ->first();

            if ($task === null) {
                $task = $this->packingTaskService
                    ->generateFromPickingList($pickingList)
                    ->firstWhere('order_id', $order->id);
            }

            if (! $task instanceof PackingTask) {
                $this->fail('Unable to create a packing task for this order.');
            }

            if ($task->status !== PackingTaskStatus::PACKED) {
                $task->update(['status' => PackingTaskStatus::IN_PROGRESS]);
            }

            return $this->orderWarehouseStatusService->setStatus($order, WarehouseFulfillmentStatus::PACKING);
        });
    }

    public function markPacked(Order $order, array $data, ?int $userId): Order
    {
        return DB::transaction(function () use ($order, $data, $userId) {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->orderWarehouseStatusService->ensureWarehouseOrder($order);
            $this->orderWarehouseStatusService->ensureCurrentStatus(
                $order,
                [WarehouseFulfillmentStatus::PACKING, WarehouseFulfillmentStatus::PICKED],
                'Packing must be started before it can be marked as packed.',
            );

            $task = PackingTask::query()
                ->where('order_id', $order->id)
                ->latest()
                ->lockForUpdate()
                ->first();

            if ($task === null) {
                $this->fail('No packing task was found for this order.');
            }

            $this->packingTaskService->complete($task, $data, $userId);

            return $this->orderWarehouseStatusService->setStatus($order, WarehouseFulfillmentStatus::PACKED);
        });
    }

    public function readyForDispatch(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->orderWarehouseStatusService->ensureWarehouseOrder($order);
            $this->orderWarehouseStatusService->ensureCurrentStatus(
                $order,
                [WarehouseFulfillmentStatus::PACKED],
                'Order must be packed before it can be marked ready for dispatch.',
            );

            return $this->orderWarehouseStatusService->setStatus($order, WarehouseFulfillmentStatus::READY_FOR_DISPATCH);
        });
    }

    private function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
