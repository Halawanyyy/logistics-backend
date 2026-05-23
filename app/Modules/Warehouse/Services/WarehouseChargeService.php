<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Finance\Services\MerchantWalletService;
use App\Modules\Warehouse\Enums\WarehouseChargeStatus;
use App\Modules\Warehouse\Models\WarehouseCharge;

class WarehouseChargeService
{
    public function __construct(
        private readonly MerchantWalletService $merchantWalletService,
    ) {
    }

    public function store(array $data, ?int $userId): WarehouseCharge
    {
        $payload = $this->buildPayload($data);
        $payload['created_by'] = $userId;

        $charge = WarehouseCharge::create($payload);
        $this->merchantWalletService->syncWarehouseChargeTransaction($charge, $userId);

        return $charge->load($this->relations());
    }

    public function update(WarehouseCharge $warehouseCharge, array $data): WarehouseCharge
    {
        $warehouseCharge->update($this->buildPayload($data, $warehouseCharge));
        $this->merchantWalletService->syncWarehouseChargeTransaction($warehouseCharge->fresh(), null);

        return $warehouseCharge->fresh()->load($this->relations());
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildPayload(array $data, ?WarehouseCharge $existing = null): array
    {
        $quantity = (float) ($data['quantity'] ?? $existing?->quantity ?? 1);
        $unitPrice = (float) ($data['unit_price'] ?? $existing?->unit_price ?? 0);

        return [
            'merchant_id' => $data['merchant_id'] ?? $existing?->merchant_id,
            'warehouse_id' => array_key_exists('warehouse_id', $data) ? $data['warehouse_id'] : $existing?->warehouse_id,
            'order_id' => array_key_exists('order_id', $data) ? $data['order_id'] : $existing?->order_id,
            'shipment_id' => array_key_exists('shipment_id', $data) ? $data['shipment_id'] : $existing?->shipment_id,
            'warehouse_product_id' => array_key_exists('warehouse_product_id', $data) ? $data['warehouse_product_id'] : $existing?->warehouse_product_id,
            'type' => $data['type'] ?? $existing?->type,
            'description' => array_key_exists('description', $data) ? $data['description'] : $existing?->description,
            'quantity' => number_format($quantity, 2, '.', ''),
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'amount' => number_format($quantity * $unitPrice, 2, '.', ''),
            'status' => $data['status'] ?? $existing?->status ?? WarehouseChargeStatus::PENDING,
            'charge_date' => $data['charge_date'] ?? $existing?->charge_date?->format('Y-m-d') ?? now()->toDateString(),
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $existing?->notes,
        ];
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'merchant',
            'warehouse',
            'warehouseProduct',
            'createdBy',
        ];
    }
}
