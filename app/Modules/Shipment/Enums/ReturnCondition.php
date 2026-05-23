<?php

namespace App\Modules\Shipment\Enums;

class ReturnCondition
{
    public const SELLABLE = 'sellable';
    public const DAMAGED = 'damaged';
    public const DISPOSED = 'disposed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::SELLABLE,
            self::DAMAGED,
            self::DISPOSED,
        ];
    }
}
