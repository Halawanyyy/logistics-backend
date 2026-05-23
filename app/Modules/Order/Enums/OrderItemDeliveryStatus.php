<?php

namespace App\Modules\Order\Enums;

class OrderItemDeliveryStatus
{
    public const PENDING = 'pending';
    public const RESERVED = 'reserved';
    public const FULFILLED = 'fulfilled';
    public const PARTIALLY_DELIVERED = 'partially_delivered';
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
            self::PENDING,
            self::RESERVED,
            self::FULFILLED,
            self::PARTIALLY_DELIVERED,
            self::DELIVERED,
            self::RETURNED,
            self::DAMAGED,
            self::CANCELLED,
        ];
    }
}
