<?php

namespace App\Modules\MerchantPortal\Enums;

final class MerchantPortalUserStatus
{
    public const INVITED = 'invited';
    public const ACTIVE = 'active';
    public const SUSPENDED = 'suspended';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::INVITED,
            self::ACTIVE,
            self::SUSPENDED,
        ];
    }
}
