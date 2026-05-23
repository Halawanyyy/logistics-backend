<?php

namespace App\Modules\Driver\Enums;

class DriverManifestStatus
{
    public const OPEN = 'open';
    public const CLOSED = 'closed';
    public const CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::OPEN,
            self::CLOSED,
            self::CANCELLED,
        ];
    }
}
