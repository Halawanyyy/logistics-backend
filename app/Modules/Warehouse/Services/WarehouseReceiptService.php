<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Warehouse\Enums\StockMovementType;
use App\Modules\Warehouse\Enums\WarehouseReceiptStatus;
use App\Modules\Warehouse\Models\WarehouseReceipt;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class WarehouseReceiptService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly WarehouseStockService $warehouseStockService,
    ) {
    }

    public function store(array $data, ?int $userId): WarehouseReceipt
    {
        return DB::transaction(function () use ($data, $userId) {
            $receipt = WarehouseReceipt::create([
                'receipt_number' => $this->generateReceiptNumber(),
                'merchant_id' => $data['merchant_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status' => WarehouseReceiptStatus::DRAFT,
                'received_by' => $userId,
                'received_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $receipt->items()->createMany($this->buildItemsPayload($data['items']));

            return $this->loadReceipt($receipt);
        });
    }

    public function update(WarehouseReceipt $warehouseReceipt, array $data): WarehouseReceipt
    {
        return DB::transaction(function () use ($warehouseReceipt, $data) {
            $receipt = WarehouseReceipt::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($warehouseReceipt->id);

            $this->ensureDraft($receipt, 'Only draft receipts can be edited.');

            $receipt->update([
                'merchant_id' => $data['merchant_id'],
                'warehouse_id' => $data['warehouse_id'],
                'notes' => $data['notes'] ?? null,
            ]);

            $receipt->items()->delete();
            $receipt->items()->createMany($this->buildItemsPayload($data['items']));

            return $this->loadReceipt($receipt);
        });
    }

    public function confirm(WarehouseReceipt $warehouseReceipt, ?int $userId): WarehouseReceipt
    {
        return DB::transaction(function () use ($warehouseReceipt, $userId) {
            $receipt = WarehouseReceipt::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($warehouseReceipt->id);

            $this->ensureDraft($receipt, 'Only draft receipts can be confirmed.');

            foreach ($receipt->items as $item) {
                if ($item->accepted_quantity <= 0) {
                    continue;
                }

                $stock = $this->warehouseStockService->lockStock(
                    (int) $receipt->warehouse_id,
                    (int) $item->warehouse_product_id,
                );

                $snapshot = $this->warehouseStockService->applyDelta(
                    $stock,
                    availableDelta: (int) $item->accepted_quantity,
                );

                $this->warehouseStockService->createMovement(
                    warehouseId: (int) $receipt->warehouse_id,
                    warehouseProductId: (int) $item->warehouse_product_id,
                    type: StockMovementType::STOCK_IN,
                    quantity: (int) $item->accepted_quantity,
                    snapshot: $snapshot,
                    referenceType: 'warehouse_receipt',
                    referenceId: (int) $receipt->id,
                    notes: $item->notes,
                    userId: $userId,
                );
            }

            $receipt->update([
                'status' => WarehouseReceiptStatus::CONFIRMED,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);

            $this->auditLogService->log(
                AuditEventType::INVENTORY_ADJUSTED,
                'confirmed',
                'warehouse_receipt',
                $receipt->id,
                [
                    'user_id' => $userId,
                    'metadata' => [
                        'warehouse_id' => $receipt->warehouse_id,
                        'merchant_id' => $receipt->merchant_id,
                    ],
                    'notes' => $receipt->notes,
                ],
            );

            return $this->loadReceipt($receipt);
        });
    }

    public function cancel(WarehouseReceipt $warehouseReceipt): WarehouseReceipt
    {
        return DB::transaction(function () use ($warehouseReceipt) {
            $receipt = WarehouseReceipt::query()
                ->lockForUpdate()
                ->findOrFail($warehouseReceipt->id);

            $this->ensureDraft($receipt, 'Only draft receipts can be cancelled.');

            $receipt->update([
                'status' => WarehouseReceiptStatus::CANCELLED,
            ]);

            return $this->loadReceipt($receipt);
        });
    }

    public function loadReceipt(WarehouseReceipt $warehouseReceipt): WarehouseReceipt
    {
        return $warehouseReceipt->fresh()->load([
            'merchant',
            'warehouse',
            'receivedBy',
            'confirmedBy',
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
            'accepted_quantity' => $item['accepted_quantity'],
            'rejected_quantity' => $item['rejected_quantity'],
            'condition' => $item['condition'] ?? null,
            'notes' => $item['notes'] ?? null,
        ], $items);
    }

    private function generateReceiptNumber(): string
    {
        $prefix = 'WRC-'.now()->format('Ymd').'-';

        $latest = WarehouseReceipt::query()
            ->lockForUpdate()
            ->where('receipt_number', 'like', $prefix.'%')
            ->orderByDesc('receipt_number')
            ->value('receipt_number');

        $nextSequence = $latest === null ? 1 : ((int) substr((string) $latest, -6)) + 1;

        return $prefix.str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }

    private function ensureDraft(WarehouseReceipt $warehouseReceipt, string $message): void
    {
        if ($warehouseReceipt->status !== WarehouseReceiptStatus::DRAFT) {
            throw new HttpResponseException(response()->json([
                'message' => $message,
            ], 422));
        }
    }
}
