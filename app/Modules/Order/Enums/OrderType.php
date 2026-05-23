<?php

namespace App\Modules\Order\Enums;

class OrderType
{
    public const DELIVERY = 'delivery';
    public const SWAP = 'swap';
    public const RETURN = 'return';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::DELIVERY,
            self::SWAP,
            self::RETURN,
        ];
    }
}
