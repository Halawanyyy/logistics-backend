<?php

namespace App\Modules\Label\Enums;

final class LabelFormat
{
    public const HTML = 'html';
    public const PDF = 'pdf';
    public const ZPL = 'zpl';
    public const TSPL = 'tspl';
    public const JSON = 'json';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::HTML,
            self::PDF,
            self::ZPL,
            self::TSPL,
            self::JSON,
        ];
    }

    /**
     * @return list<string>
     */
    public static function manifestValues(): array
    {
        return [
            self::HTML,
            self::PDF,
            self::JSON,
        ];
    }
}
