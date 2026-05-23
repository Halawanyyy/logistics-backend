<?php

namespace App\Modules\Finance\Enums;

class MerchantWalletDirection
{
    public const CREDIT = 'credit';
    public const DEBIT = 'debit';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::CREDIT,
            self::DEBIT,
        ];
    }
}
