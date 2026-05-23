<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Settings\Services\SystemSettingService;
use App\Modules\Warehouse\Enums\StockMovementType;
use App\Modules\Warehouse\Enums\WarehouseReturnCondition;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\WarehouseReturn;
use Illuminate\Support\Facades\DB;

class WarehouseReturnService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly SystemSettingService $systemSettingService,
    ) {
    }

    public function store(array $data, ?int $userId): WarehouseReturn
    {
        return DB::transaction(function () use ($data, $userId) {
            $condition = $data['condition']
                ?? (string) $this->systemSettingService->get('default_return_condition', WarehouseReturnCondition::SELLABLE);
            $condition = in_array($condition, WarehouseReturnCondition::values(), true)
                ? $condition
                : WarehouseReturnCondition::SELLABLE;
            $autoRestoreSellableReturns = (bool) $this->systemSettingService->get('auto_restore_sellable_returns', true);

            $stock = InventoryStock::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    [
                        'warehouse_id' => $data['warehouse_id'],
                        'warehouse_product_id' => $data['warehouse_product_id'],
                    ],
                    [
                        'quantity_available' => 0,
                        'quantity_reserved' => 0,
                        'quantity_damaged' => 0,
                    ]
                );

            $beforeAvailable = $stock->quantity_available;
            $beforeReserved = $stock->quantity_reserved;
            $beforeDamaged = $stock->quantity_damaged;

            $afterAvailable = $beforeAvailable;
            $afterReserved = $beforeReserved;
            $afterDamaged = $beforeDamaged;
            $movementType = StockMovementType::ADJUSTMENT;

            if ($condition === WarehouseReturnCondition::SELLABLE && $autoRestoreSellableReturns) {
                $afterAvailable += (int) $data['quantity'];
                $movementType = StockMovementType::IN;
            } elseif ($condition === WarehouseReturnCondition::DAMAGED) {
                $afterDamaged += (int) $data['quantity'];
                $movementType = StockMovementType::DAMAGED;
            }

            $stock->update([
                'quantity_available' => $afterAvailable,
                'quantity_reserved' => $afterReserved,
                'quantity_damaged' => $afterDamaged,
            ]);

            $warehouseReturn = WarehouseReturn::create([
                'shipment_id' => $data['shipment_id'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'warehouse_product_id' => $data['warehouse_product_id'],
                'quantity' => $data['quantity'],
                'condition' => $condition,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            StockMovement::create([
                'warehouse_id' => $data['warehouse_id'],
                'warehouse_product_id' => $data['warehouse_product_id'],
                'type' => $movementType,
                'quantity' => $data['quantity'],
                'before_available' => $beforeAvailable,
                'after_available' => $afterAvailable,
                'before_reserved' => $beforeReserved,
                'after_reserved' => $afterReserved,
                'before_damaged' => $beforeDamaged,
                'after_damaged' => $afterDamaged,
                'reference_type' => 'warehouse_return',
                'reference_id' => $warehouseReturn->id,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $this->auditLogService->log(
                AuditEventType::WAREHOUSE_RETURN_CREATED,
                'created',
                'warehouse_product',
                (int) $data['warehouse_product_id'],
                [
                    'user_id' => $userId,
                    'new_values' => [
                        'quantity' => $data['quantity'],
                        'condition' => $condition,
                    ],
                    'metadata' => [
                        'warehouse_return_id' => $warehouseReturn->id,
                        'warehouse_id' => $data['warehouse_id'],
                        'order_id' => $data['order_id'] ?? null,
                        'shipment_id' => $data['shipment_id'] ?? null,
                    ],
                    'notes' => $data['notes'] ?? null,
                ],
            );

            return $warehouseReturn->load([
                'warehouse',
                'warehouseProduct.merchant',
                'createdBy',
            ]);
        });
    }
}
