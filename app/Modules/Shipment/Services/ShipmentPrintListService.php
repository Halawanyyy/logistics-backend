<?php

namespace App\Modules\Shipment\Services;

use App\Modules\Shipment\Models\Shipment;
use Illuminate\Database\Eloquent\Builder;

class ShipmentPrintListService
{
    /**
     * @param array<string, mixed> $filters
     * @return Builder<Shipment>
     */
    public function query(array $filters): Builder
    {
        return Shipment::query()
            ->with(['merchant', 'deliveryGovernorate', 'deliveryArea', 'assignedDriver'])
            ->when(isset($filters['merchant_id']), fn (Builder $query) => $query->where('merchant_id', $filters['merchant_id']))
            ->when(isset($filters['assigned_driver_id']), fn (Builder $query) => $query->where('assigned_driver_id', $filters['assigned_driver_id']))
            ->when(isset($filters['delivery_governorate_id']), fn (Builder $query) => $query->where('delivery_governorate_id', $filters['delivery_governorate_id']))
            ->when(isset($filters['delivery_area_id']), fn (Builder $query) => $query->where('delivery_area_id', $filters['delivery_area_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->whereDate('created_at', $filters['date'])
            ->latest();
    }
}
