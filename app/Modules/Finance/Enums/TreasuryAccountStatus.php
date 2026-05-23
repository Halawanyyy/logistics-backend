<?php

namespace App\Modules\Finance\Enums;

class TreasuryAccountStatus
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::ACTIVE,
            self::INACTIVE,
        ];
    }
}
