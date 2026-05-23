<?php

namespace App\Modules\Warehouse\Enums;

class PickingListItemStatus
{
    public const PENDING = 'pending';
    public const PICKED = 'picked';
    public const PARTIALLY_PICKED = 'partially_picked';
    public const MISSING = 'missing';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::PENDING,
            self::PICKED,
            self::PARTIALLY_PICKED,
            self::MISSING,
        ];
    }
}
