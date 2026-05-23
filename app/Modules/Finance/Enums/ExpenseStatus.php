<?php

namespace App\Modules\Finance\Enums;

class ExpenseStatus
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const PAID = 'paid';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::PENDING,
            self::APPROVED,
            self::PAID,
            self::REJECTED,
            self::CANCELLED,
        ];
    }
}
