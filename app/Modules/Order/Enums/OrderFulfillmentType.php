<?php

namespace App\Modules\Order\Enums;

class OrderFulfillmentType
{
    public const PICKUP_FROM_MERCHANT = 'pickup_from_merchant';
    public const FROM_WAREHOUSE = 'from_warehouse';
    public const PICKUP_FROM_CUSTOMER = 'pickup_from_customer';
    public const MERCHANT_PICKUP_ALIAS = 'merchant_pickup';
    public const WAREHOUSE_FULFILLMENT_ALIAS = 'warehouse_fulfillment';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::PICKUP_FROM_MERCHANT,
            self::FROM_WAREHOUSE,
            self::PICKUP_FROM_CUSTOMER,
        ];
    }

    /**
     * @return list<string>
     */
    public static function inputValues(): array
    {
        return [
            ...self::values(),
            self::MERCHANT_PICKUP_ALIAS,
            self::WAREHOUSE_FULFILLMENT_ALIAS,
        ];
    }

    public static function normalize(?string $value): ?string
    {
        return match ($value) {
            self::MERCHANT_PICKUP_ALIAS => self::PICKUP_FROM_MERCHANT,
            self::WAREHOUSE_FULFILLMENT_ALIAS => self::FROM_WAREHOUSE,
            default => $value,
        };
    }
}
