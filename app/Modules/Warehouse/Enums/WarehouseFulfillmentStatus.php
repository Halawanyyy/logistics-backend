<?php

namespace App\Modules\Warehouse\Enums;

class WarehouseFulfillmentStatus
{
    public const NOT_REQUIRED = 'not_required';
    public const PENDING_RESERVATION = 'pending_reservation';
    public const RESERVED = 'reserved';
    public const PICKING = 'picking';
    public const PICKED = 'picked';
    public const PACKING = 'packing';
    public const PACKED = 'packed';
    public const READY_FOR_DISPATCH = 'ready_for_dispatch';
    public const FULFILLED = 'fulfilled';
    public const CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::NOT_REQUIRED,
            self::PENDING_RESERVATION,
            self::RESERVED,
            self::PICKING,
            self::PICKED,
            self::PACKING,
            self::PACKED,
            self::READY_FOR_DISPATCH,
            self::FULFILLED,
            self::CANCELLED,
        ];
    }
}
