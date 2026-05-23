<?php

namespace App\Modules\Shipment\Enums;

class ShipmentItemResultStatus
{
    public const DELIVERED = 'delivered';
    public const RETURNED = 'returned';
    public const DAMAGED = 'damaged';
    public const CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::DELIVERED,
            self::RETURNED,
            self::DAMAGED,
            self::CANCELLED,
        ];
    }
}
