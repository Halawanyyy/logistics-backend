<?php

namespace App\Modules\Shipment\Enums;

class DeliveryAttemptResult
{
    public const FAILED = 'failed';
    public const POSTPONED = 'postponed';
    public const CUSTOMER_UNREACHABLE = 'customer_unreachable';
    public const CUSTOMER_REFUSED = 'customer_refused';
    public const RESCHEDULED = 'rescheduled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::FAILED,
            self::POSTPONED,
            self::CUSTOMER_UNREACHABLE,
            self::CUSTOMER_REFUSED,
            self::RESCHEDULED,
        ];
    }
}
