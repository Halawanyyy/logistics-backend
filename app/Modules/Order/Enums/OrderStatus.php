<?php

namespace App\Modules\Order\Enums;

class OrderStatus
{
    public const DRAFT = 'draft';
    public const PENDING_REVIEW = 'pending_review';
    public const CONFIRMED = 'confirmed';
    public const SHIPMENT_CREATED = 'shipment_created';
    public const PARTIALLY_DELIVERED = 'partially_delivered';
    public const DELIVERED = 'delivered';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::DRAFT,
            self::PENDING_REVIEW,
            self::CONFIRMED,
            self::SHIPMENT_CREATED,
            self::PARTIALLY_DELIVERED,
            self::DELIVERED,
            self::FAILED,
            self::CANCELLED,
        ];
    }
}
