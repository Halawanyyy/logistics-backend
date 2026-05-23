<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\Shipment;
use Illuminate\Database\Eloquent\Builder;

class DashboardSummaryService
{
    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getSummary(array $filters): array
    {
        $baseQuery = Shipment::query()
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['merchant_id']), fn (Builder $query) => $query->where('merchant_id', $filters['merchant_id']))
            ->when(isset($filters['assigned_driver_id']), fn (Builder $query) => $query->where('assigned_driver_id', $filters['assigned_driver_id']));

        $completedShipments = (clone $baseQuery)
            ->financiallyCompleted()
            ->with([
                'order:id,payment_type',
                'order.items:id,order_id,delivered_quantity,unit_price',
            ])
            ->get(['id', 'order_id', 'shipping_fee']);

        $companyProfit = $completedShipments->sum(fn ($shipment) => (float) $shipment->shipping_fee);
        $merchantsPayables = $completedShipments->sum(function ($shipment): float {
            if ($shipment->order?->payment_type !== 'cod') {
                return 0;
            }

            $codAmount = (float) $shipment->delivered_cod_amount;
            $shippingFee = (float) $shipment->shipping_fee;

            if ($codAmount <= 0) {
                return 0;
            }

            return max($codAmount - $shippingFee, 0);
        });
        $codCollected = $completedShipments->sum(function ($shipment): float {
            return $shipment->order?->payment_type === 'cod'
                ? (float) $shipment->delivered_cod_amount
                : 0;
        });

        return [
            'company_profit' => number_format($companyProfit, 2, '.', ''),
            'merchants_payables' => number_format($merchantsPayables, 2, '.', ''),
            'cod_collected' => number_format($codCollected, 2, '.', ''),
            'shipments' => [
                'total' => (clone $baseQuery)->count(),
                'pending_pickup' => (clone $baseQuery)->where('status', ShipmentStatus::PENDING_PICKUP)->count(),
                'in_transit' => (clone $baseQuery)->whereIn('status', ShipmentStatus::dashboardInTransitValues())->count(),
                'delivered' => (clone $baseQuery)->where('status', ShipmentStatus::DELIVERED)->count(),
                'assigned' => (clone $baseQuery)->whereNotNull('assigned_driver_id')->count(),
                'unassigned' => (clone $baseQuery)->whereNull('assigned_driver_id')->count(),
            ],
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'merchant_id' => $filters['merchant_id'] ?? null,
                'assigned_driver_id' => $filters['assigned_driver_id'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getMerchantSummary(int $merchantId, array $filters): array
    {
        $baseQuery = Shipment::query()
            ->where('merchant_id', $merchantId)
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['assigned_driver_id']), fn (Builder $query) => $query->where('assigned_driver_id', $filters['assigned_driver_id']));

        $completedShipments = (clone $baseQuery)
            ->financiallyCompleted()
            ->with([
                'order:id,payment_type',
                'order.items:id,order_id,delivered_quantity,unit_price',
            ])
            ->get(['id', 'order_id', 'shipping_fee']);

        $companyProfit = $completedShipments->sum(fn ($shipment) => (float) $shipment->shipping_fee);
        $merchantsPayables = $completedShipments->sum(function ($shipment): float {
            if ($shipment->order?->payment_type !== 'cod') {
                return 0;
            }

            $codAmount = (float) $shipment->delivered_cod_amount;
            $shippingFee = (float) $shipment->shipping_fee;

            if ($codAmount <= 0) {
                return 0;
            }

            return max($codAmount - $shippingFee, 0);
        });

        $codCollected = $completedShipments->sum(function ($shipment): float {
            return $shipment->order?->payment_type === 'cod'
                ? (float) $shipment->delivered_cod_amount
                : 0;
        });

        return [
            'company_profit' => number_format($companyProfit, 2, '.', ''),
            'merchants_payables' => number_format($merchantsPayables, 2, '.', ''),
            'cod_collected' => number_format((float) $codCollected, 2, '.', ''),
            'shipments' => [
                'total' => (clone $baseQuery)->count(),
                'pending_pickup' => (clone $baseQuery)->where('status', ShipmentStatus::PENDING_PICKUP)->count(),
                'in_transit' => (clone $baseQuery)->whereIn('status', ShipmentStatus::dashboardInTransitValues())->count(),
                'delivered' => (clone $baseQuery)->where('status', ShipmentStatus::DELIVERED)->count(),
                'assigned' => (clone $baseQuery)->whereNotNull('assigned_driver_id')->count(),
                'unassigned' => (clone $baseQuery)->whereNull('assigned_driver_id')->count(),
            ],
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'merchant_id' => $merchantId,
                'assigned_driver_id' => $filters['assigned_driver_id'] ?? null,
            ],
        ];
    }
}
