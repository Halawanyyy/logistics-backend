<?php

namespace App\Modules\Warehouse\Enums;

class WarehouseReceiptStatus
{
    public const DRAFT = 'draft';
    public const CONFIRMED = 'confirmed';
    public const CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::DRAFT,
            self::CONFIRMED,
            self::CANCELLED,
        ];
    }
}
