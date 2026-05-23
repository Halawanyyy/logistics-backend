<?php

namespace App\Modules\Finance\Enums;

class PayoutStatus
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const COMPLETED = 'completed';
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
            self::COMPLETED,
            self::REJECTED,
            self::CANCELLED,
        ];
    }
}
