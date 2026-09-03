<?php

namespace App\Modules\Waybill\Enums;

final class WaybillStatus
{
    public const AVAILABLE = 'available';

    public const ASSIGNED = 'assigned';

    public const VOID = 'void';

    /** @return list<string> */
    public static function values(): array
    {
        return [self::AVAILABLE, self::ASSIGNED, self::VOID];
    }
}
