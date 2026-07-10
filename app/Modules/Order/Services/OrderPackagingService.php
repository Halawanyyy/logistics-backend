<?php

namespace App\Modules\Order\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\CompanyCatalog\Models\CompanyCatalogItem;
use App\Modules\Order\Models\Order;
use Illuminate\Support\Arr;

class OrderPackagingService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly OrderPackagingChargeService $orderPackagingChargeService,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function syncFromPayload(Order $order, array $validated, ?int $userId = null): void
    {
        $order->loadMissing('packagingItems');

        $oldValues = $this->auditSnapshot($order);
        $hasPackagingItems = Arr::exists($validated, 'packaging_items');
        $packagingItems = $hasPackagingItems ? ($validated['packaging_items'] ?? []) : null;
        $requiresPackaging = Arr::exists($validated, 'requires_packaging')
            ? filter_var($validated['requires_packaging'], FILTER_VALIDATE_BOOL)
            : (bool) $order->requires_packaging;

        if (is_array($packagingItems) && $packagingItems !== []) {
            $requiresPackaging = true;
        }

        if (! $requiresPackaging) {
            $order->packagingItems()->delete();

            if ((bool) $order->requires_packaging !== false || (float) $order->total_packaging_fee !== 0.0) {
                $order->forceFill([
                    'requires_packaging' => false,
                    'total_packaging_fee' => '0.00',
                ])->save();
            }

            $this->syncAuditAndCharge($order, $oldValues, $userId);

            return;
        }

        if ($hasPackagingItems) {
            $snapshots = $this->buildSnapshotPayload($packagingItems);
            $totalPackagingFee = array_sum(array_map(
                static fn (array $item): float => (float) $item['total_price'],
                $snapshots
            ));

            $order->packagingItems()->delete();

            if ($snapshots !== []) {
                $order->packagingItems()->createMany($snapshots);
            }

            $order->forceFill([
                'requires_packaging' => true,
                'total_packaging_fee' => number_format($totalPackagingFee, 2, '.', ''),
            ])->save();

            $this->syncAuditAndCharge($order, $oldValues, $userId);

            return;
        }

        if (! $order->requires_packaging) {
            $order->forceFill([
                'requires_packaging' => true,
                'total_packaging_fee' => number_format((float) $order->packagingItems()->sum('total_price'), 2, '.', ''),
            ])->save();
        }

        $this->syncAuditAndCharge($order, $oldValues, $userId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildSnapshotPayload(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $catalogItems = CompanyCatalogItem::query()
            ->whereIn('id', array_column($items, 'company_catalog_item_id'))
            ->get()
            ->keyBy('id');

        return array_map(function (array $item) use ($catalogItems): array {
            /** @var CompanyCatalogItem $catalogItem */
            $catalogItem = $catalogItems->get($item['company_catalog_item_id']);
            $quantity = (int) ($item['quantity'] ?? 1);
            $unitPrice = (float) $catalogItem->unit_price;

            return [
                'company_catalog_item_id' => $catalogItem->id,
                'item_name' => $catalogItem->name,
                'item_sku' => $catalogItem->sku,
                'category' => $catalogItem->category,
                'size_label' => $catalogItem->size_label,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'quantity' => $quantity,
                'total_price' => number_format($unitPrice * $quantity, 2, '.', ''),
                'notes' => $item['notes'] ?? null,
            ];
        }, $items);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditSnapshot(Order $order): array
    {
        return [
            'requires_packaging' => (bool) $order->requires_packaging,
            'total_packaging_fee' => number_format((float) $order->total_packaging_fee, 2, '.', ''),
            'packaging_items' => $order->packagingItems
                ->map(fn ($item) => [
                    'company_catalog_item_id' => $item->company_catalog_item_id,
                    'item_name' => $item->item_name,
                    'item_sku' => $item->item_sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'notes' => $item->notes,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $oldValues
     */
    private function syncAuditAndCharge(Order $order, array $oldValues, ?int $userId): void
    {
        $order->load('packagingItems');
        $newValues = $this->auditSnapshot($order);

        if ($oldValues !== $newValues) {
            $this->auditLogService->log(
                AuditEventType::ORDER_PACKAGING_ITEMS_UPDATED,
                'updated',
                $order,
                null,
                [
                    'user_id' => $userId,
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                    'notes' => 'Order packaging items updated.',
                ],
            );
        }

        if ($order->status === 'confirmed') {
            $this->orderPackagingChargeService->sync($order, $userId);
        }
    }
}
