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
            $stock = InventoryStock::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    [
                        'warehouse_id' => $warehouseId,
                        'warehouse_product_id' => $warehouseProductId,
                    ],
                    [
                        'warehouse_bin_id' => $warehouseBinId,
                        'quantity_available' => 0,
                        'quantity_reserved' => 0,
                        'quantity_damaged' => 0,
                    ]
                );

            if ($warehouseBinId !== null && (int) $stock->warehouse_bin_id !== $warehouseBinId) {
                $stock->warehouse_bin_id = $warehouseBinId;
            }

            $beforeAvailable = $stock->quantity_available;
            $beforeReserved = $stock->quantity_reserved;
            $beforeDamaged = $stock->quantity_damaged;

            $afterAvailable = $beforeAvailable;
            $afterReserved = $beforeReserved;
            $afterDamaged = $beforeDamaged;

            if ($type === StockMovementType::IN) {
                $afterAvailable += $quantity;
            } elseif ($type === StockMovementType::OUT) {
                if ($beforeAvailable < $quantity) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'Not enough available stock.',
                    ], 422));
                }

                $afterAvailable -= $quantity;
            } elseif ($type === StockMovementType::DAMAGED) {
                if ($beforeAvailable < $quantity) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'Not enough available stock.',
                    ], 422));
                }

                $afterAvailable -= $quantity;
                $afterDamaged += $quantity;
            } elseif ($type === StockMovementType::ADJUSTMENT) {
                $afterAvailable = $quantity;
            }

            $stock->update([
                'warehouse_bin_id' => $stock->warehouse_bin_id,
                'quantity_available' => $afterAvailable,
                'quantity_reserved' => $afterReserved,
                'quantity_damaged' => $afterDamaged,
            ]);

            StockMovement::create([
                'warehouse_id' => $warehouseId,
                'warehouse_product_id' => $warehouseProductId,
                'type' => $type,
                'quantity' => $quantity,
                'before_available' => $beforeAvailable,
                'after_available' => $afterAvailable,
                'before_reserved' => $beforeReserved,
                'after_reserved' => $afterReserved,
                'before_damaged' => $beforeDamaged,
                'after_damaged' => $afterDamaged,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            $this->auditLogService->log(
                AuditEventType::INVENTORY_ADJUSTED,
                'adjusted',
                'warehouse_product',
                $warehouseProductId,
                [
                    'user_id' => $userId,
                    'old_values' => [
                        'quantity_available' => $beforeAvailable,
                        'quantity_reserved' => $beforeReserved,
                        'quantity_damaged' => $beforeDamaged,
                    ],
                    'new_values' => [
                        'quantity_available' => $afterAvailable,
                        'quantity_reserved' => $afterReserved,
                        'quantity_damaged' => $afterDamaged,
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

            return $stock->fresh();
        });
    }
}
