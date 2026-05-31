<?php

namespace App\Modules\Warehouse\Enums;

class StockMovementType
{
    public const IN = 'in';
    public const OUT = 'out';
    public const RESERVED = 'reserved';
    public const RELEASED = 'released';
    public const DAMAGED = 'damaged';
    public const ADJUSTMENT = 'adjustment';
    public const STOCK_IN = 'stock_in';
    public const RETURN_RECEIVED = 'return_received';
    public const RETURN_RESTOCKED = 'return_restocked';
    public const RETURN_DAMAGED = 'return_damaged';
    public const RETURN_REJECTED = 'return_rejected';
    public const STOCK_TRANSFER_OUT = 'stock_transfer_out';
    public const STOCK_TRANSFER_IN = 'stock_transfer_in';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::IN,
            self::OUT,
            self::RESERVED,
            self::RELEASED,
            self::DAMAGED,
            self::ADJUSTMENT,
            self::STOCK_IN,
            self::RETURN_RECEIVED,
            self::RETURN_RESTOCKED,
            self::RETURN_DAMAGED,
            self::RETURN_REJECTED,
            self::STOCK_TRANSFER_OUT,
            self::STOCK_TRANSFER_IN,
        ];
    }
}
