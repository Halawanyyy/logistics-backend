<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Settings\Services\SystemSettingService;
use App\Modules\Warehouse\Enums\StockMovementType;
use App\Modules\Warehouse\Enums\WarehouseReturnCondition;
use App\Modules\Warehouse\Enums\WarehouseReturnStatus;
use App\Modules\Warehouse\Models\WarehouseReturn;
use App\Modules\Warehouse\Models\WarehouseReturnItem;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WarehouseReturnService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly SystemSettingService $systemSettingService,
        private readonly WarehouseStockService $warehouseStockService,
    ) {
    }

    public function store(array $data, ?int $userId): WarehouseReturn
    {
        return DB::transaction(function () use ($data, $userId) {
            $items = $this->normalizeItems($data);
            $primaryItem = $items[0];
            $warehouseReturn = WarehouseReturn::create([
                'shipment_id' => $data['shipment_id'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'warehouse_product_id' => $data['warehouse_product_id'] ?? $primaryItem['warehouse_product_id'],
                'quantity' => array_sum(array_column($items, 'expected_quantity')),
                'status' => WarehouseReturnStatus::PENDING,
                'condition' => $data['condition'] ?? $primaryItem['condition'] ?? WarehouseReturnCondition::SELLABLE,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $warehouseReturn->items()->createMany($items);

            $this->auditLogService->log(
                AuditEventType::WAREHOUSE_RETURN_CREATED,
                'created',
                'warehouse_return',
                $warehouseReturn->id,
                [
                    'user_id' => $userId,
                    'metadata' => [
                        'warehouse_id' => $warehouseReturn->warehouse_id,
                        'order_id' => $warehouseReturn->order_id,
                        'shipment_id' => $warehouseReturn->shipment_id,
                    ],
                    'notes' => $warehouseReturn->notes,
                ],
            );

            $isLegacySingleItem = ! isset($data['items']);
            $autoProcessLegacySingleItem = $isLegacySingleItem && ! ((bool) ($data['defer_processing'] ?? false));

            if ($autoProcessLegacySingleItem) {
                $receivedPayload = [
                    'items' => [[
                        'warehouse_return_item_id' => $warehouseReturn->items()->value('id'),
                        'received_quantity' => (int) $data['quantity'],
                    ]],
                    'notes' => $data['notes'] ?? null,
                ];
                $inspectPayload = [
                    'items' => [[
                        'warehouse_return_item_id' => $warehouseReturn->items()->value('id'),
                        'condition' => $data['condition'],
                        'notes' => $data['notes'] ?? null,
                    ]],
                    'notes' => $data['notes'] ?? null,
                ];

                $warehouseReturn = $this->receive($warehouseReturn, $receivedPayload, $userId);
                $warehouseReturn = $this->inspect($warehouseReturn, $inspectPayload, $userId);

                if (($data['condition'] ?? WarehouseReturnCondition::SELLABLE) === WarehouseReturnCondition::SELLABLE) {
                    $warehouseReturn = $this->restock($warehouseReturn, [
                        'items' => [[
                            'warehouse_return_item_id' => $warehouseReturn->items()->first()->id,
                            'quantity' => (int) $data['quantity'],
                            'notes' => $data['notes'] ?? null,
                        ]],
                        'notes' => $data['notes'] ?? null,
                    ], $userId);
                } elseif (($data['condition'] ?? '') === WarehouseReturnCondition::DAMAGED) {
                    $warehouseReturn = $this->markDamaged($warehouseReturn, [
                        'items' => [[
                            'warehouse_return_item_id' => $warehouseReturn->items()->first()->id,
                            'quantity' => (int) $data['quantity'],
                            'notes' => $data['notes'] ?? null,
                        ]],
                        'notes' => $data['notes'] ?? null,
                    ], $userId);
                } else {
                    $warehouseReturn = $this->reject($warehouseReturn, [
                        'items' => [[
                            'warehouse_return_item_id' => $warehouseReturn->items()->first()->id,
                            'quantity' => (int) $data['quantity'],
                            'notes' => $data['notes'] ?? null,
                        ]],
                        'notes' => $data['notes'] ?? null,
                    ], $userId);
                }
            }

            return $this->loadWarehouseReturn($warehouseReturn);
        });
    }

    public function updateStatus(WarehouseReturn $warehouseReturn, array $data, ?int $userId): WarehouseReturn
    {
        return DB::transaction(function () use ($warehouseReturn, $data, $userId) {
            $warehouseReturn = WarehouseReturn::query()
                ->lockForUpdate()
                ->findOrFail($warehouseReturn->id);

            $this->ensureNotClosed($warehouseReturn);

            $warehouseReturn->update([
                'status' => $data['status'],
                'notes' => $data['notes'] ?? $warehouseReturn->notes,
            ]);

            if ($data['status'] === WarehouseReturnStatus::CLOSED) {
                $warehouseReturn->update([
                    'closed_by' => $userId,
                    'closed_at' => now(),
                ]);
            }

            return $this->loadWarehouseReturn($warehouseReturn);
        });
    }

    public function receive(WarehouseReturn $warehouseReturn, array $data, ?int $userId): WarehouseReturn
    {
        return DB::transaction(function () use ($warehouseReturn, $data, $userId) {
            $warehouseReturn = WarehouseReturn::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($warehouseReturn->id);

            if ($warehouseReturn->status !== WarehouseReturnStatus::PENDING) {
                $this->fail('Return must be pending before it can be received.');
            }

            $items = $this->mapItemsById($warehouseReturn->items);
            $totalReceived = 0;

            foreach ($data['items'] as $itemData) {
                /** @var WarehouseReturnItem|null $item */
                $item = $items->get((int) $itemData['warehouse_return_item_id']);

                if ($item === null) {
                    $this->fail('Selected return item does not belong to this return.');
                }

                if ((int) $itemData['received_quantity'] > (int) $item->expected_quantity) {
                    $this->fail('Received quantity cannot exceed expected quantity.');
                }

                $item->update([
                    'received_quantity' => (int) $itemData['received_quantity'],
                ]);

                $totalReceived += (int) $itemData['received_quantity'];

                $stock = $this->warehouseStockService->lockStock((int) $warehouseReturn->warehouse_id, (int) $item->warehouse_product_id);
                $snapshot = $this->warehouseStockService->applyDelta($stock);

                $this->warehouseStockService->createMovement(
                    warehouseId: (int) $warehouseReturn->warehouse_id,
                    warehouseProductId: (int) $item->warehouse_product_id,
                    type: StockMovementType::RETURN_RECEIVED,
                    quantity: (int) $itemData['received_quantity'],
                    snapshot: $snapshot,
                    referenceType: 'warehouse_return',
                    referenceId: (int) $warehouseReturn->id,
                    notes: $data['notes'] ?? $item->notes,
                    userId: $userId,
                    orderId: $warehouseReturn->order_id,
                    shipmentId: $warehouseReturn->shipment_id,
                );
            }

            $warehouseReturn->update([
                'quantity' => $totalReceived,
                'status' => WarehouseReturnStatus::RECEIVED,
                'received_by' => $userId,
                'received_at' => now(),
                'notes' => $data['notes'] ?? $warehouseReturn->notes,
            ]);

            return $this->loadWarehouseReturn($warehouseReturn);
        });
    }

    public function inspect(WarehouseReturn $warehouseReturn, array $data, ?int $userId): WarehouseReturn
    {
        return DB::transaction(function () use ($warehouseReturn, $data, $userId) {
            $warehouseReturn = WarehouseReturn::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($warehouseReturn->id);

            if ($warehouseReturn->status !== WarehouseReturnStatus::RECEIVED) {
                $this->fail('Return must be received before it can be inspected.');
            }

            $items = $this->mapItemsById($warehouseReturn->items);

            foreach ($data['items'] as $itemData) {
                /** @var WarehouseReturnItem|null $item */
                $item = $items->get((int) $itemData['warehouse_return_item_id']);

                if ($item === null) {
                    $this->fail('Selected return item does not belong to this return.');
                }

                $item->update([
                    'condition' => $itemData['condition'],
                    'notes' => $itemData['notes'] ?? $item->notes,
                ]);
            }

            $warehouseReturn->update([
                'status' => WarehouseReturnStatus::INSPECTED,
                'inspected_by' => $userId,
                'inspected_at' => now(),
                'notes' => $data['notes'] ?? $warehouseReturn->notes,
            ]);

            return $this->loadWarehouseReturn($warehouseReturn);
        });
    }

    public function restock(WarehouseReturn $warehouseReturn, array $data, ?int $userId): WarehouseReturn
    {
        return $this->processItems(
            $warehouseReturn,
            $data,
            $userId,
            'restock_quantity',
            StockMovementType::RETURN_RESTOCKED,
            WarehouseReturnStatus::RESTOCKED,
            availableDeltaSign: 1,
        );
    }

    public function markDamaged(WarehouseReturn $warehouseReturn, array $data, ?int $userId): WarehouseReturn
    {
        return $this->processItems(
            $warehouseReturn,
            $data,
            $userId,
            'damaged_quantity',
            StockMovementType::RETURN_DAMAGED,
            WarehouseReturnStatus::DAMAGED,
            damagedDeltaSign: 1,
        );
    }

    public function reject(WarehouseReturn $warehouseReturn, array $data, ?int $userId): WarehouseReturn
    {
        return $this->processItems(
            $warehouseReturn,
            $data,
            $userId,
            'rejected_quantity',
            StockMovementType::RETURN_REJECTED,
            WarehouseReturnStatus::REJECTED,
        );
    }

    public function close(WarehouseReturn $warehouseReturn, ?string $notes, ?int $userId): WarehouseReturn
    {
        return DB::transaction(function () use ($warehouseReturn, $notes, $userId) {
            $warehouseReturn = WarehouseReturn::query()
                ->lockForUpdate()
                ->findOrFail($warehouseReturn->id);

            $this->ensureNotClosed($warehouseReturn);

            $warehouseReturn->update([
                'status' => WarehouseReturnStatus::CLOSED,
                'closed_by' => $userId,
                'closed_at' => now(),
                'notes' => $notes ?? $warehouseReturn->notes,
            ]);

            return $this->loadWarehouseReturn($warehouseReturn);
        });
    }

    public function loadWarehouseReturn(WarehouseReturn $warehouseReturn): WarehouseReturn
    {
        return $warehouseReturn->fresh()->load([
            'warehouse',
            'warehouseProduct.merchant',
            'createdBy',
            'items.warehouseProduct',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $data): array
    {
        if (isset($data['items'])) {
            return array_map(static fn (array $item): array => [
                'warehouse_product_id' => $item['warehouse_product_id'],
                'expected_quantity' => $item['expected_quantity'],
                'received_quantity' => 0,
                'restock_quantity' => 0,
                'damaged_quantity' => 0,
                'rejected_quantity' => 0,
                'condition' => $item['condition'] ?? null,
                'notes' => $item['notes'] ?? null,
            ], $data['items']);
        }

        return [[
            'warehouse_product_id' => $data['warehouse_product_id'],
            'expected_quantity' => $data['quantity'],
            'received_quantity' => 0,
            'restock_quantity' => 0,
            'damaged_quantity' => 0,
            'rejected_quantity' => 0,
            'condition' => $data['condition'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]];
    }

    /**
     * @param  Collection<int, WarehouseReturnItem>  $items
     * @return Collection<int, WarehouseReturnItem>
     */
    private function mapItemsById(Collection $items): Collection
    {
        return $items->keyBy('id');
    }

    private function ensureNotClosed(WarehouseReturn $warehouseReturn): void
    {
        if ($warehouseReturn->status === WarehouseReturnStatus::CLOSED) {
            $this->fail('Closed return cannot be modified.');
        }
    }

    private function processItems(
        WarehouseReturn $warehouseReturn,
        array $data,
        ?int $userId,
        string $quantityColumn,
        string $movementType,
        string $status,
        int $availableDeltaSign = 0,
        int $damagedDeltaSign = 0,
    ): WarehouseReturn {
        return DB::transaction(function () use ($warehouseReturn, $data, $userId, $quantityColumn, $movementType, $status, $availableDeltaSign, $damagedDeltaSign) {
            $warehouseReturn = WarehouseReturn::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($warehouseReturn->id);

            if (! in_array($warehouseReturn->status, [WarehouseReturnStatus::INSPECTED, WarehouseReturnStatus::RESTOCKED, WarehouseReturnStatus::DAMAGED, WarehouseReturnStatus::REJECTED], true)) {
                $this->fail('Return must be inspected before this action can be performed.');
            }

            $items = $this->mapItemsById($warehouseReturn->items);

            foreach ($data['items'] as $itemData) {
                /** @var WarehouseReturnItem|null $item */
                $item = $items->get((int) $itemData['warehouse_return_item_id']);

                if ($item === null) {
                    $this->fail('Selected return item does not belong to this return.');
                }

                $quantity = (int) $itemData['quantity'];
                $alreadyProcessed = (int) $item->restock_quantity + (int) $item->damaged_quantity + (int) $item->rejected_quantity;

                if ($alreadyProcessed + $quantity > (int) $item->received_quantity) {
                    $this->fail('Processed quantity cannot exceed received quantity.');
                }

                $stock = $this->warehouseStockService->lockStock((int) $warehouseReturn->warehouse_id, (int) $item->warehouse_product_id);
                $snapshot = $this->warehouseStockService->applyDelta(
                    $stock,
                    availableDelta: $availableDeltaSign * $quantity,
                    damagedDelta: $damagedDeltaSign * $quantity,
                );

                $item->update([
                    $quantityColumn => (int) $item->{$quantityColumn} + $quantity,
                    'notes' => $itemData['notes'] ?? $item->notes,
                ]);

                $this->warehouseStockService->createMovement(
                    warehouseId: (int) $warehouseReturn->warehouse_id,
                    warehouseProductId: (int) $item->warehouse_product_id,
                    type: $movementType,
                    quantity: $quantity,
                    snapshot: $snapshot,
                    referenceType: 'warehouse_return',
                    referenceId: (int) $warehouseReturn->id,
                    notes: $itemData['notes'] ?? $data['notes'] ?? $item->notes,
                    userId: $userId,
                    orderId: $warehouseReturn->order_id,
                    shipmentId: $warehouseReturn->shipment_id,
                );
            }

            $updateData = [
                'status' => $status,
                'notes' => $data['notes'] ?? $warehouseReturn->notes,
            ];

            if ($status === WarehouseReturnStatus::RESTOCKED) {
                $updateData['restocked_by'] = $userId;
                $updateData['restocked_at'] = now();
            } elseif ($status === WarehouseReturnStatus::DAMAGED) {
                $updateData['damaged_by'] = $userId;
                $updateData['damaged_at'] = now();
            } elseif ($status === WarehouseReturnStatus::REJECTED) {
                $updateData['rejected_by'] = $userId;
                $updateData['rejected_at'] = now();
            }

            $warehouseReturn->update($updateData);

            return $this->loadWarehouseReturn($warehouseReturn);
        });
    }

    private function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
