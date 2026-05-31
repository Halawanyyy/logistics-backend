<?php

namespace App\Modules\MerchantPortal\Enums;

class MerchantInboundStockRequestStatus
{
    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';
    public const APPROVED = 'approved';
    public const SCHEDULED = 'scheduled';
    public const PARTIALLY_RECEIVED = 'partially_received';
    public const RECEIVED = 'received';
    public const CANCELLED = 'cancelled';
    public const REJECTED = 'rejected';
    public const CLOSED = 'closed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::DRAFT,
            self::SUBMITTED,
            self::APPROVED,
            self::SCHEDULED,
            self::PARTIALLY_RECEIVED,
            self::RECEIVED,
            self::CANCELLED,
            self::REJECTED,
            self::CLOSED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function cancellableValues(): array
    {
        return [
            self::DRAFT,
            self::SUBMITTED,
            self::APPROVED,
            self::SCHEDULED,
        ];
    }
}
