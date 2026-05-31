<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Warehouse\Enums\StockMovementType;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\StockMovement;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly WarehouseStockService $warehouseStockService,
    ) {
    }

    public function adjust(int $warehouseId, int $warehouseProductId, ?int $warehouseBinId, string $type, int $quantity, ?string $notes, ?int $userId): InventoryStock
    {
        if (in_array($type, [StockMovementType::RESERVED, StockMovementType::RELEASED], true)) {
            throw new HttpResponseException(response()->json([
                'message' => 'This stock movement type is not supported yet.',
            ], 422));
        }

        return DB::transaction(function () use ($warehouseId, $warehouseProductId, $warehouseBinId, $type, $quantity, $notes, $userId) {
            $stock = $this->warehouseStockService->lockStock($warehouseId, $warehouseProductId, $warehouseBinId);
            $availableDelta = 0;
            $damagedDelta = 0;

            if ($type === StockMovementType::IN) {
                $availableDelta = $quantity;
            } elseif ($type === StockMovementType::OUT) {
                $availableDelta = -$quantity;
            } elseif ($type === StockMovementType::DAMAGED) {
                $availableDelta = -$quantity;
                $damagedDelta = $quantity;
            } elseif ($type === StockMovementType::ADJUSTMENT) {
                $availableDelta = $quantity - (int) $stock->quantity_available;
            }

            $snapshot = $this->warehouseStockService->applyDelta(
                $stock,
                availableDelta: $availableDelta,
                damagedDelta: $damagedDelta,
                warehouseBinId: $warehouseBinId,
            );

            $this->warehouseStockService->createMovement(
                warehouseId: $warehouseId,
                warehouseProductId: $warehouseProductId,
                type: $type,
                quantity: $quantity,
                snapshot: $snapshot,
                notes: $notes,
                userId: $userId,
            );

            $this->auditLogService->log(
                AuditEventType::INVENTORY_ADJUSTED,
                'adjusted',
                'warehouse_product',
                $warehouseProductId,
                [
                    'user_id' => $userId,
                    'old_values' => [
                        'quantity_available' => $snapshot['before_available'],
                        'quantity_reserved' => $snapshot['before_reserved'],
                        'quantity_damaged' => $snapshot['before_damaged'],
                    ],
                    'new_values' => [
                        'quantity_available' => $snapshot['after_available'],
                        'quantity_reserved' => $snapshot['after_reserved'],
                        'quantity_damaged' => $snapshot['after_damaged'],
                    ],
                    'metadata' => [
                        'warehouse_id' => $warehouseId,
                        'warehouse_bin_id' => $warehouseBinId,
                        'movement_type' => $type,
                        'quantity' => $quantity,
                    ],
                    'notes' => $notes,
                ],
            );

            /** @var InventoryStock $freshStock */
            $freshStock = $snapshot['stock'];

            return $freshStock;
        });
    }
}
