<?php

namespace App\Modules\Warehouse\Enums;

class StorageFeeCalculationType
{
    public const PER_ITEM_PER_MONTH = 'per_item_per_month';
    public const PER_SKU_PER_MONTH = 'per_sku_per_month';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::PER_ITEM_PER_MONTH,
            self::PER_SKU_PER_MONTH,
        ];
    }
}
