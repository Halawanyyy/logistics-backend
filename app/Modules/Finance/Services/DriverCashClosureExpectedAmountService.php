<?php

namespace App\Modules\Finance\Services;

use App\Modules\Driver\Models\Driver;
use App\Modules\Order\Enums\PaymentType;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\Shipment;

class DriverCashClosureExpectedAmountService
{
    public function calculate(Driver $driver, string $date): string
    {
        $expectedAmount = Shipment::query()
            ->where('assigned_driver_id', $driver->id)
            ->financiallyCompleted()
            ->whereDate('updated_at', $date)
            ->with([
                'order:id,payment_type',
                'order.items:id,order_id,delivered_quantity,unit_price',
            ])
            ->get(['id', 'order_id', 'cod_amount', 'shipping_fee'])
            ->sum(function (Shipment $shipment): float {
                $deliveredCodAmount = (float) $shipment->delivered_cod_amount;

                return $shipment->order?->payment_type === PaymentType::COD
                    ? ($deliveredCodAmount > 0 ? $deliveredCodAmount : (float) $shipment->cod_amount)
                    : 0;
            });

        return number_format($expectedAmount, 2, '.', '');
    }
}
