<?php

namespace App\Modules\LocationPricing\Enums;

class PricingPlanStatus
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::ACTIVE,
            self::INACTIVE,
        ];
    }
}
