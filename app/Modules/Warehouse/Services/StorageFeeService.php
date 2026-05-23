<?php

namespace App\Modules\Warehouse\Services;

use App\Models\Merchant;
use App\Modules\Warehouse\Enums\StorageFeeCalculationType;
use App\Modules\Warehouse\Enums\WarehouseChargeType;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\StorageFeeRule;
use App\Modules\Warehouse\Models\WarehouseCharge;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorageFeeService
{
    public function __construct(
        private readonly WarehouseChargeService $warehouseChargeService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(Merchant $merchant, string $periodStart, string $periodEnd, ?int $userId): array
    {
        $rule = $this->resolveRule($merchant);

        if ($rule === null) {
            $this->fail('No active storage fee rule found for this merchant.');
        }

        $description = sprintf('Auto storage fee %s to %s', $periodStart, $periodEnd);

        $duplicateExists = WarehouseCharge::query()
            ->where('merchant_id', $merchant->id)
            ->where('type', WarehouseChargeType::STORAGE)
            ->where('description', $description)
            ->exists();

        if ($duplicateExists) {
            $this->fail('Storage fees have already been generated for this merchant and period.');
        }

        $inventoryQuery = InventoryStock::query()
            ->with('warehouseProduct')
            ->whereHas('warehouseProduct', fn ($query) => $query->where('merchant_id', $merchant->id));

        $stocks = $inventoryQuery->get();

        if ($rule->calculation_type === StorageFeeCalculationType::PER_SKU_PER_MONTH) {
            $billableUnits = max(
                $stocks
                    ->filter(fn ($stock) => ((int) $stock->quantity_available + (int) $stock->quantity_reserved + (int) $stock->quantity_damaged) > 0)
                    ->pluck('warehouse_product_id')
                    ->unique()
                    ->count() - (int) $rule->free_units,
                0,
            );
        } else {
            $billableUnits = max(
                $stocks->sum(fn ($stock) => (int) $stock->quantity_available + (int) $stock->quantity_reserved + (int) $stock->quantity_damaged) - (int) $rule->free_units,
                0,
            );
        }

        $charge = $this->warehouseChargeService->store([
            'merchant_id' => $merchant->id,
            'type' => WarehouseChargeType::STORAGE,
            'description' => $description,
            'quantity' => $billableUnits > 0 ? $billableUnits : 1,
            'unit_price' => $billableUnits > 0 ? $rule->unit_price : 0,
            'notes' => $rule->notes,
        ], $userId);

        return [
            'merchant_id' => $merchant->id,
            'merchant_name' => $merchant->name,
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'calculation_type' => $rule->calculation_type,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'billable_units' => $billableUnits,
            'charge' => $charge,
        ];
    }

    private function resolveRule(Merchant $merchant): ?StorageFeeRule
    {
        return StorageFeeRule::query()
            ->where('status', 'active')
            ->where('merchant_id', $merchant->id)
            ->latest()
            ->first()
            ?? StorageFeeRule::query()
                ->where('status', 'active')
                ->whereNull('merchant_id')
                ->where('pricing_plan_id', $merchant->pricing_plan_id)
                ->latest()
                ->first();
    }

    private function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
