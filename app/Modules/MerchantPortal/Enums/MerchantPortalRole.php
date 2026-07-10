<?php

namespace App\Modules\MerchantPortal\Enums;

final class MerchantPortalRole
{
    public const OWNER = 'owner';
    public const ADMIN = 'admin';
    public const STAFF = 'staff';
    public const ACCOUNTANT = 'accountant';
    public const WAREHOUSE_VIEWER = 'warehouse_viewer';
    public const VIEWER = 'viewer';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::OWNER,
            self::ADMIN,
            self::STAFF,
            self::ACCOUNTANT,
            self::WAREHOUSE_VIEWER,
            self::VIEWER,
        ];
    }
}
