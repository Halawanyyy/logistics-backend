<?php

namespace App\Modules\Warehouse\Enums;

class PackingTaskStatus
{
    public const PENDING = 'pending';
    public const IN_PROGRESS = 'in_progress';
    public const PACKED = 'packed';
    public const CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::PENDING,
            self::IN_PROGRESS,
            self::PACKED,
            self::CANCELLED,
        ];
    }
}
