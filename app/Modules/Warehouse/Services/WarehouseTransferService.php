<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Warehouse\Enums\StockMovementType;
use App\Modules\Warehouse\Enums\WarehouseTransferStatus;
use App\Modules\Warehouse\Models\WarehouseTransfer;
use App\Modules\Warehouse\Models\WarehouseTransferItem;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WarehouseTransferService
{
    public function __construct(
        private readonly WarehouseStockService $warehouseStockService,
    ) {
    }

    public function store(array $data, ?int $userId): WarehouseTransfer
    {
        return DB::transaction(function () use ($data, $userId) {
            $transfer = WarehouseTransfer::create([
                'transfer_number' => $this->generateTransferNumber(),
                'source_warehouse_id' => $data['source_warehouse_id'],
                'destination_warehouse_id' => $data['destination_warehouse_id'],
                'status' => WarehouseTransferStatus::DRAFT,
                'created_by' => $userId,
                'notes' => $data['notes'] ?? null,
            ]);

            $transfer->items()->createMany($this->buildItemsPayload($data['items']));

            return $this->loadTransfer($transfer);
        });
    }

    public function update(WarehouseTransfer $warehouseTransfer, array $data): WarehouseTransfer
    {
        return DB::transaction(function () use ($warehouseTransfer, $data) {
            $transfer = WarehouseTransfer::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($warehouseTransfer->id);

            if ($transfer->status !== WarehouseTransferStatus::DRAFT) {
                $this->fail('Only draft transfers can be edited.');
            }

            $transfer->update([
                'source_warehouse_id' => $data['source_warehouse_id'],
                'destination_warehouse_id' => $data['destination_warehouse_id'],
                'notes' => $data['notes'] ?? null,
            ]);

            $transfer->items()->delete();
            $transfer->items()->createMany($this->buildItemsPayload($data['items']));

            return $this->loadTransfer($transfer);
        });
    }

    public function confirm(WarehouseTransfer $warehouseTransfer, ?int $userId): WarehouseTransfer
    {
        return DB::transaction(function () use ($warehouseTransfer, $userId) {
            $transfer = WarehouseTransfer::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($warehouseTransfer->id);

            if ($transfer->status !== WarehouseTransferStatus::DRAFT) {
                $this->fail('Only draft transfers can be confirmed.');
            }

            foreach ($transfer->items as $item) {
                $stock = $this->warehouseStockService->lockStock((int) $transfer->source_warehouse_id, (int) $item->warehouse_product_id);

                if ((int) $stock->quantity_available < (int) $item->quantity) {
                    $this->fail('Cannot confirm transfer because source warehouse stock is insufficient.');
                }
            }

            $transfer->update([
                'status' => WarehouseTransferStatus::CONFIRMED,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);

            return $this->loadTransfer($transfer);
        });
    }

    public function dispatch(WarehouseTransfer $warehouseTransfer, ?int $userId): WarehouseTransfer
    {
        return DB::transaction(function () use ($warehouseTransfer, $userId) {
            $transfer = WarehouseTransfer::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($warehouseTransfer->id);

            if ($transfer->status !== WarehouseTransferStatus::CONFIRMED) {
                $this->fail('Only confirmed transfers can be dispatched.');
            }

            foreach ($transfer->items as $item) {
                $stock = $this->warehouseStockService->lockStock((int) $transfer->source_warehouse_id, (int) $item->warehouse_product_id);

                if ((int) $stock->quantity_available < (int) $item->quantity) {
                    $this->fail('Not enough available stock to dispatch this transfer.');
                }

                $snapshot = $this->warehouseStockService->applyDelta(
                    $stock,
                    availableDelta: -((int) $item->quantity),
                );

                $this->warehouseStockService->createMovement(
                    warehouseId: (int) $transfer->source_warehouse_id,
                    warehouseProductId: (int) $item->warehouse_product_id,
                    type: StockMovementType::STOCK_TRANSFER_OUT,
                    quantity: (int) $item->quantity,
                    snapshot: $snapshot,
                    referenceType: 'warehouse_transfer',
                    referenceId: (int) $transfer->id,
                    notes: $item->notes,
                    userId: $userId,
                );
            }

            $transfer->update([
                'status' => WarehouseTransferStatus::IN_TRANSIT,
                'dispatched_by' => $userId,
                'dispatched_at' => now(),
            ]);

            return $this->loadTransfer($transfer);
        });
    }

    public function receive(WarehouseTransfer $warehouseTransfer, array $data, ?int $userId): WarehouseTransfer
    {
        return DB::transaction(function () use ($warehouseTransfer, $data, $userId) {
            $transfer = WarehouseTransfer::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($warehouseTransfer->id);

            if ($transfer->status !== WarehouseTransferStatus::IN_TRANSIT) {
                $this->fail('Only in-transit transfers can be received.');
            }

            $receivedQuantities = collect($data['items'] ?? [])
                ->mapWithKeys(fn (array $item): array => [(int) $item['warehouse_transfer_item_id'] => (int) $item['received_quantity']]);

            foreach ($transfer->items as $item) {
                $receivedQuantity = $receivedQuantities->get($item->id, (int) $item->quantity);

                if ($receivedQuantity > (int) $item->quantity) {
                    $this->fail('Received quantity cannot exceed transfer quantity.');
                }

                $stock = $this->warehouseStockService->lockStock((int) $transfer->destination_warehouse_id, (int) $item->warehouse_product_id);
                $snapshot = $this->warehouseStockService->applyDelta(
                    $stock,
                    availableDelta: $receivedQuantity,
                );

                $item->update([
                    'received_quantity' => $receivedQuantity,
                ]);

                $this->warehouseStockService->createMovement(
                    warehouseId: (int) $transfer->destination_warehouse_id,
                    warehouseProductId: (int) $item->warehouse_product_id,
                    type: StockMovementType::STOCK_TRANSFER_IN,
                    quantity: $receivedQuantity,
                    snapshot: $snapshot,
                    referenceType: 'warehouse_transfer',
                    referenceId: (int) $transfer->id,
                    notes: $item->notes ?? ($data['notes'] ?? null),
                    userId: $userId,
                );
            }

            $transfer->update([
                'status' => WarehouseTransferStatus::RECEIVED,
                'received_by' => $userId,
                'received_at' => now(),
                'notes' => $data['notes'] ?? $transfer->notes,
            ]);

            return $this->loadTransfer($transfer);
        });
    }

    public function cancel(WarehouseTransfer $warehouseTransfer): WarehouseTransfer
    {
        return DB::transaction(function () use ($warehouseTransfer) {
            $transfer = WarehouseTransfer::query()
                ->lockForUpdate()
                ->findOrFail($warehouseTransfer->id);

            if (! in_array($transfer->status, [WarehouseTransferStatus::DRAFT, WarehouseTransferStatus::CONFIRMED], true)) {
                $this->fail('Dispatched or received transfers cannot be cancelled safely.');
            }

            $transfer->update([
                'status' => WarehouseTransferStatus::CANCELLED,
            ]);

            return $this->loadTransfer($transfer);
        });
    }

    public function loadTransfer(WarehouseTransfer $warehouseTransfer): WarehouseTransfer
    {
        return $warehouseTransfer->fresh()->load([
            'sourceWarehouse',
            'destinationWarehouse',
            'createdBy',
            'confirmedBy',
            'dispatchedBy',
            'receivedBy',
            'items.warehouseProduct',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildItemsPayload(array $items): array
    {
        return array_map(static fn (array $item): array => [
            'warehouse_product_id' => $item['warehouse_product_id'],
            'quantity' => $item['quantity'],
            'received_quantity' => $item['received_quantity'] ?? 0,
            'notes' => $item['notes'] ?? null,
        ], $items);
    }

    private function generateTransferNumber(): string
    {
        $prefix = 'WTR-'.now()->format('Ymd').'-';

        $latest = WarehouseTransfer::query()
            ->lockForUpdate()
            ->where('transfer_number', 'like', $prefix.'%')
            ->orderByDesc('transfer_number')
            ->value('transfer_number');

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
