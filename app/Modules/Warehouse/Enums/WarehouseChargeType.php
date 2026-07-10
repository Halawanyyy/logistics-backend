<?php

namespace App\Modules\Warehouse\Enums;

class WarehouseChargeType
{
    public const STORAGE = 'storage';

    public const PACKAGING = 'packaging';

    public const PACKAGING_PRODUCT = 'packaging_product';

    public const PACKAGING_SERVICE = 'packaging_service';

    public const FULFILLMENT = 'fulfillment';

    public const RETURN_HANDLING = 'return_handling';

    public const ADJUSTMENT = 'adjustment';

    public const OTHER = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::STORAGE,
            self::PACKAGING,
            self::PACKAGING_PRODUCT,
            self::PACKAGING_SERVICE,
            self::FULFILLMENT,
            self::RETURN_HANDLING,
            self::ADJUSTMENT,
            self::OTHER,
        ];
    }
}
