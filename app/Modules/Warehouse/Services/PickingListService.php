<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Models\Order;
use App\Modules\Warehouse\Enums\OrderStockReservationStatus;
use App\Modules\Warehouse\Enums\PickingListItemStatus;
use App\Modules\Warehouse\Enums\PickingListStatus;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\PickingList;
use App\Modules\Warehouse\Models\PickingListItem;
use App\Modules\Warehouse\Models\WarehouseProduct;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PickingListService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function generate(array $data, ?int $userId): PickingList
    {
        return DB::transaction(function () use ($data, $userId) {
            $orders = Order::query()
                ->with(['items', 'stockReservations'])
                ->whereIn('id', $data['order_ids'])
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                $this->fail('No orders found for picking list generation.');
            }

            $pickingList = PickingList::create([
                'picking_number' => $this->generatePickingNumber(),
                'warehouse_id' => $data['warehouse_id'],
                'assigned_to' => $data['assigned_to'] ?? null,
                'status' => PickingListStatus::PENDING,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $createdItems = 0;

            foreach ($orders as $order) {
                if ($order->fulfillment_type !== OrderFulfillmentType::FROM_WAREHOUSE) {
                    continue;
                }

                $reservations = $order->stockReservations
                    ->where('warehouse_id', (int) $data['warehouse_id'])
                    ->whereIn('status', [
                        OrderStockReservationStatus::RESERVED,
                        OrderStockReservationStatus::FULFILLED,
                    ]);

                foreach ($reservations as $reservation) {
                    $inventoryStock = InventoryStock::query()
                        ->where('warehouse_id', $reservation->warehouse_id)
                        ->where('warehouse_product_id', $reservation->warehouse_product_id)
                        ->first();

                    PickingListItem::create([
                        'picking_list_id' => $pickingList->id,
                        'order_id' => $order->id,
                        'order_item_id' => $reservation->order_item_id,
                        'warehouse_product_id' => $reservation->warehouse_product_id,
                        'warehouse_bin_id' => $inventoryStock?->warehouse_bin_id,
                        'quantity_required' => $reservation->quantity,
                        'quantity_picked' => 0,
                        'status' => PickingListItemStatus::PENDING,
                    ]);

                    $createdItems++;
                }
            }

            if ($createdItems === 0) {
                $this->fail('No warehouse-reserved items available for picking list generation.');
            }

            return $this->loadPickingList($pickingList);
        });
    }

    public function scanPick(PickingList $pickingList, WarehouseProduct $warehouseProduct, int $quantity): PickingList
    {
        return DB::transaction(function () use ($pickingList, $warehouseProduct, $quantity) {
            $pickingList = PickingList::query()->lockForUpdate()->findOrFail($pickingList->id);

            if ($pickingList->status === PickingListStatus::CANCELLED) {
                $this->fail('Cancelled picking list cannot be scanned.');
            }

            $item = $pickingList->items()
                ->where('warehouse_product_id', $warehouseProduct->id)
                ->whereIn('status', [PickingListItemStatus::PENDING, PickingListItemStatus::PARTIALLY_PICKED])
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                $this->fail('No pending picking list item matches this barcode.');
            }

            $remaining = (int) $item->quantity_required - (int) $item->quantity_picked;

            if ($quantity > $remaining) {
                $this->fail('Scanned quantity cannot exceed remaining quantity required.');
            }

            $newQuantityPicked = (int) $item->quantity_picked + $quantity;

            $item->update([
                'quantity_picked' => $newQuantityPicked,
                'status' => $newQuantityPicked >= (int) $item->quantity_required
                    ? PickingListItemStatus::PICKED
                    : PickingListItemStatus::PARTIALLY_PICKED,
            ]);

            $allPicked = ! $pickingList->items()
                ->where('status', '!=', PickingListItemStatus::PICKED)
                ->exists();

            $pickingList->update([
                'status' => $allPicked ? PickingListStatus::COMPLETED : PickingListStatus::IN_PROGRESS,
                'picked_at' => $allPicked ? now() : $pickingList->picked_at,
            ]);

            if ($allPicked) {
                $this->auditLogService->log(
                    AuditEventType::PICKING_COMPLETED,
                    'completed',
                    $pickingList->fresh(),
                    null,
                    [
                        'metadata' => [
                            'warehouse_product_id' => $warehouseProduct->id,
                            'quantity_scanned' => $quantity,
                        ],
                        'notes' => 'Picking list completed.',
                    ],
                );
            }

            return $this->loadPickingList($pickingList);
        });
    }

    public function cancel(PickingList $pickingList): PickingList
    {
        if ($pickingList->status === PickingListStatus::COMPLETED) {
            $this->fail('Completed picking list cannot be cancelled.');
        }

        $pickingList->update([
            'status' => PickingListStatus::CANCELLED,
        ]);

        return $this->loadPickingList($pickingList);
    }

    public function loadPickingList(PickingList $pickingList): PickingList
    {
        return $pickingList->fresh()->load([
            'warehouse',
            'assignedTo',
            'createdBy',
            'items.order',
            'items.orderItem',
            'items.warehouseProduct',
            'items.warehouseBin.zone',
            'items.warehouseBin.shelf',
        ]);
    }

    private function generatePickingNumber(): string
    {
        $datePart = now()->format('Ymd');
        $prefix = 'PKL-'.$datePart.'-';

        $latest = PickingList::withTrashed()
            ->where('picking_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('picking_number')
            ->value('picking_number');

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
