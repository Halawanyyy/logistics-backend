<?php

namespace App\Modules\Finance\Enums;

class TreasuryAccountType
{
    public const CASH = 'cash';
    public const BANK = 'bank';
    public const WALLET = 'wallet';
    public const OTHER = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::CASH,
            self::BANK,
            self::WALLET,
            self::OTHER,
        ];
    }
}
