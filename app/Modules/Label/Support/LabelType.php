<?php

namespace App\Modules\Label\Support;

class LabelType
{
    public const WAREHOUSE_PRODUCT = 'warehouse_product';
    public const SHIPMENT = 'shipment';
    public const ORDER = 'order';
    public const WAREHOUSE_BIN = 'warehouse_bin';
    public const DRIVER_MANIFEST = 'driver_manifest';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::WAREHOUSE_PRODUCT,
            self::SHIPMENT,
            self::ORDER,
            self::WAREHOUSE_BIN,
            self::DRIVER_MANIFEST,
        ];
    }
}
