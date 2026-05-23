<?php

namespace App\Modules\Shipment\Enums;

class ShipmentStatus
{
    public const PENDING_PICKUP = 'pending_pickup';
    public const DRIVER_ASSIGNED = 'driver_assigned';
    public const PICKED_UP = 'picked_up';
    public const ARRIVED_AT_HUB = 'arrived_at_hub';
    public const SORTING = 'sorting';
    public const READY_FOR_DISPATCH = 'ready_for_dispatch';
    public const IN_TRANSIT = 'in_transit';
    public const OUT_FOR_DELIVERY = 'out_for_delivery';
    public const DELIVERY_ATTEMPTED = 'delivery_attempted';
    public const RESCHEDULED = 'rescheduled';
    public const DELIVERED = 'delivered';
    public const PARTIALLY_DELIVERED = 'partially_delivered';
    public const FAILED = 'failed';
    public const RETURNED = 'returned';
    public const RETURNED_TO_HUB = 'returned_to_hub';
    public const RETURNED_TO_WAREHOUSE = 'returned_to_warehouse';
    public const CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::PENDING_PICKUP,
            self::DRIVER_ASSIGNED,
            self::PICKED_UP,
            self::ARRIVED_AT_HUB,
            self::SORTING,
            self::READY_FOR_DISPATCH,
            self::IN_TRANSIT,
            self::OUT_FOR_DELIVERY,
            self::DELIVERY_ATTEMPTED,
            self::RESCHEDULED,
            self::DELIVERED,
            self::PARTIALLY_DELIVERED,
            self::FAILED,
            self::RETURNED,
            self::RETURNED_TO_HUB,
            self::RETURNED_TO_WAREHOUSE,
            self::CANCELLED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function dashboardInTransitValues(): array
    {
        return [
            self::PENDING_PICKUP,
            self::DRIVER_ASSIGNED,
            self::PICKED_UP,
            self::ARRIVED_AT_HUB,
            self::SORTING,
            self::READY_FOR_DISPATCH,
            self::IN_TRANSIT,
            self::OUT_FOR_DELIVERY,
            self::DELIVERY_ATTEMPTED,
            self::RESCHEDULED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function financiallyCompletedValues(): array
    {
        return [
            self::DELIVERED,
            self::PARTIALLY_DELIVERED,
        ];
    }
}
