<?php

namespace App\Modules\Order\Services;

use App\Models\Merchant;
use App\Modules\LocationPricing\Enums\PricingPlanStatus;
use App\Modules\LocationPricing\Models\PricingPlanRate;
use App\Modules\LocationPricing\Models\ShippingRate;
use App\Modules\Order\Enums\OrderType;

class OrderShippingFeeService
{
    /**
     * @return array{shipping_fee:string, source:string|null}
     */
    public function resolve(int $merchantId, int $governorateId, ?int $areaId, string $orderType = OrderType::DELIVERY): array
    {
        $merchant = Merchant::query()
            ->select(['id', 'pricing_plan_id'])
            ->with(['pricingPlan' => fn ($query) => $query->select(['id', 'status'])])
            ->find($merchantId);

        if (
            $merchant?->pricing_plan_id !== null
            && $merchant->pricingPlan !== null
            && $merchant->pricingPlan->status === PricingPlanStatus::ACTIVE
        ) {
            $planRate = $this->findPricingPlanRate($merchant->pricing_plan_id, $governorateId, $areaId);

            if ($planRate !== null) {
                return [
                    'shipping_fee' => $this->resolvePlanFee($planRate, $orderType),
                    'source' => $planRate->area_id !== null ? 'pricing_plan_area' : 'pricing_plan_governorate',
                ];
            }
        }

        if ($areaId !== null) {
            $areaRate = ShippingRate::query()
                ->where('governorate_id', $governorateId)
                ->where('area_id', $areaId)
                ->where('is_active', true)
                ->first();

            if ($areaRate !== null) {
                return [
                    'shipping_fee' => (string) $areaRate->shipping_fee,
                    'source' => 'area',
                ];
            }
        }

        $governorateRate = ShippingRate::query()
            ->where('governorate_id', $governorateId)
            ->whereNull('area_id')
            ->where('is_active', true)
            ->first();

        if ($governorateRate !== null) {
            return [
                'shipping_fee' => (string) $governorateRate->shipping_fee,
                'source' => 'governorate',
            ];
        }

        return [
            'shipping_fee' => '0.00',
            'source' => null,
        ];
    }

    private function findPricingPlanRate(int $pricingPlanId, int $governorateId, ?int $areaId): ?PricingPlanRate
    {
        if ($areaId !== null) {
            $areaRate = PricingPlanRate::query()
                ->where('pricing_plan_id', $pricingPlanId)
                ->where('governorate_id', $governorateId)
                ->where('area_id', $areaId)
                ->where('is_active', true)
                ->first();

            if ($areaRate !== null) {
                return $areaRate;
            }
        }

        return PricingPlanRate::query()
            ->where('pricing_plan_id', $pricingPlanId)
            ->where('governorate_id', $governorateId)
            ->whereNull('area_id')
            ->where('is_active', true)
            ->first();
    }

    private function resolvePlanFee(PricingPlanRate $pricingPlanRate, string $orderType): string
    {
        $feeField = match ($orderType) {
            OrderType::SWAP => 'swap_fee',
            OrderType::RETURN => 'return_fee',
            default => 'delivery_fee',
        };

        return (string) $pricingPlanRate->{$feeField};
    }
}
