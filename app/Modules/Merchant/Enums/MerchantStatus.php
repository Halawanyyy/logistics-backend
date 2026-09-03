<?php

namespace App\Modules\Merchant\Enums;

final class MerchantStatus
{
    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const REJECTED = 'rejected';

    public const SUSPENDED = 'suspended';

    /** Retained for compatibility with existing admin-created merchants. */
    public const INACTIVE = 'inactive';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::PENDING,
            self::ACTIVE,
            self::REJECTED,
            self::SUSPENDED,
            self::INACTIVE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function registrationValues(): array
    {
        return [
            self::PENDING,
            self::ACTIVE,
            self::REJECTED,
            self::SUSPENDED,
        ];
    }
}
