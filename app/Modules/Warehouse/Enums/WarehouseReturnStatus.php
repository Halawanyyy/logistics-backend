<?php

namespace App\Modules\Warehouse\Enums;

class WarehouseReturnStatus
{
    public const PENDING = 'pending';
    public const RECEIVED = 'received';
    public const INSPECTED = 'inspected';
    public const RESTOCKED = 'restocked';
    public const DAMAGED = 'damaged';
    public const REJECTED = 'rejected';
    public const CLOSED = 'closed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::PENDING,
            self::RECEIVED,
            self::INSPECTED,
            self::RESTOCKED,
            self::DAMAGED,
            self::REJECTED,
            self::CLOSED,
        ];
    }
}
