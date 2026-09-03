<?php

namespace App\Modules\Waybill\Enums;

final class WaybillSource
{
    public const AUTOMATIC = 'automatic';

    public const PREPRINTED = 'preprinted';

    /** @return list<string> */
    public static function values(): array
    {
        return [self::AUTOMATIC, self::PREPRINTED];
    }
}
