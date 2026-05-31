<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\StockMovement;
use Illuminate\Http\Exceptions\HttpResponseException;

class WarehouseStockService
{
    public function lockStock(int $warehouseId, int $warehouseProductId, ?int $warehouseBinId = null): InventoryStock
    {
        return InventoryStock::query()
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
    }

    /**
     * @return array<string, int|InventoryStock>
     */
    public function applyDelta(
        InventoryStock $stock,
        int $availableDelta = 0,
        int $reservedDelta = 0,
        int $damagedDelta = 0,
        ?int $warehouseBinId = null,
    ): array {
        $beforeAvailable = (int) $stock->quantity_available;
        $beforeReserved = (int) $stock->quantity_reserved;
        $beforeDamaged = (int) $stock->quantity_damaged;

        $afterAvailable = $beforeAvailable + $availableDelta;
        $afterReserved = $beforeReserved + $reservedDelta;
        $afterDamaged = $beforeDamaged + $damagedDelta;

        if ($afterAvailable < 0 || $afterReserved < 0 || $afterDamaged < 0) {
            throw new HttpResponseException(response()->json([
                'message' => 'Stock quantity cannot become negative.',
            ], 422));
        }

        if ($warehouseBinId !== null) {
            $stock->warehouse_bin_id = $warehouseBinId;
        }

        $stock->update([
            'warehouse_bin_id' => $stock->warehouse_bin_id,
            'quantity_available' => $afterAvailable,
            'quantity_reserved' => $afterReserved,
            'quantity_damaged' => $afterDamaged,
        ]);

        return [
            'stock' => $stock->fresh(),
            'before_available' => $beforeAvailable,
            'after_available' => $afterAvailable,
            'before_reserved' => $beforeReserved,
            'after_reserved' => $afterReserved,
            'before_damaged' => $beforeDamaged,
            'after_damaged' => $afterDamaged,
        ];
    }

    /**
     * @param  array<string, int|string|null>  $snapshot
     */
    public function createMovement(
        int $warehouseId,
        int $warehouseProductId,
        string $type,
        int $quantity,
        array $snapshot,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $userId = null,
        ?int $orderId = null,
        ?int $shipmentId = null,
    ): StockMovement {
        return StockMovement::create([
            'warehouse_id' => $warehouseId,
            'warehouse_product_id' => $warehouseProductId,
            'order_id' => $orderId,
            'shipment_id' => $shipmentId,
            'type' => $type,
            'quantity' => $quantity,
            'before_available' => $snapshot['before_available'],
            'after_available' => $snapshot['after_available'],
            'before_reserved' => $snapshot['before_reserved'],
            'after_reserved' => $snapshot['after_reserved'],
            'before_damaged' => $snapshot['before_damaged'],
            'after_damaged' => $snapshot['after_damaged'],
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $userId,
        ]);
    }
}
