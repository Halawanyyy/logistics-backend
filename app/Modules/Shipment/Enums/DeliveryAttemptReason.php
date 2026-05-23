<?php

namespace App\Modules\Shipment\Enums;

class DeliveryAttemptReason
{
    public const CUSTOMER_NOT_ANSWERING = 'customer_not_answering';
    public const CUSTOMER_REFUSED = 'customer_refused';
    public const WRONG_ADDRESS = 'wrong_address';
    public const NO_CASH = 'no_cash';
    public const POSTPONED_BY_CUSTOMER = 'postponed_by_customer';
    public const AREA_CLOSED = 'area_closed';
    public const DAMAGED_PACKAGE = 'damaged_package';
    public const CUSTOMER_REQUESTED_RESCHEDULE = 'customer_requested_reschedule';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::CUSTOMER_NOT_ANSWERING,
            self::CUSTOMER_REFUSED,
            self::WRONG_ADDRESS,
            self::NO_CASH,
            self::POSTPONED_BY_CUSTOMER,
            self::AREA_CLOSED,
            self::DAMAGED_PACKAGE,
            self::CUSTOMER_REQUESTED_RESCHEDULE,
        ];
    }
}
