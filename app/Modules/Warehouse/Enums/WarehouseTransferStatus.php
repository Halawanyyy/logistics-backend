<?php

namespace App\Modules\Warehouse\Enums;

class WarehouseTransferStatus
{
    public const DRAFT = 'draft';
    public const CONFIRMED = 'confirmed';
    public const IN_TRANSIT = 'in_transit';
    public const RECEIVED = 'received';
    public const CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::DRAFT,
            self::CONFIRMED,
            self::IN_TRANSIT,
            self::RECEIVED,
            self::CANCELLED,
        ];
    }
}
