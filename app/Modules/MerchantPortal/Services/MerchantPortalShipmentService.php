<?php

namespace App\Modules\MerchantPortal\Services;

use App\Modules\Shipment\Models\Shipment;
use Illuminate\Database\Eloquent\Builder;

class MerchantPortalShipmentService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function queryForMerchant(int $merchantId, array $filters = []): Builder
    {
        return Shipment::query()
            ->with($this->relations())
            ->where('merchant_id', $merchantId)
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['payment_type']), fn ($query) => $query->whereHas('order', fn ($orderQuery) => $orderQuery->where('payment_type', $filters['payment_type'])))
            ->when(($filters['cod_only'] ?? false) === true, fn ($query) => $query->where('cod_amount', '>', 0))
            ->when(isset($filters['delivery_governorate_id']), fn ($query) => $query->where('delivery_governorate_id', $filters['delivery_governorate_id']))
            ->when(isset($filters['delivery_area_id']), fn ($query) => $query->where('delivery_area_id', $filters['delivery_area_id']))
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['shipment_number']), fn ($query) => $query->where('shipment_number', 'like', '%'.$filters['shipment_number'].'%'))
            ->when(isset($filters['customer_phone']), fn ($query) => $query->where('customer_phone', 'like', '%'.$filters['customer_phone'].'%'))
            ->when(($filters['has_driver'] ?? null) === true, fn ($query) => $query->whereNotNull('assigned_driver_id'))
            ->when(($filters['has_driver'] ?? null) === false, fn ($query) => $query->whereNull('assigned_driver_id'))
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('shipment_number', 'like', '%'.$search.'%')
                        ->orWhere('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('customer_phone', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%');
                });
            })
            ->latest();
    }

    public function findOwned(int $merchantId, int $shipmentId): Shipment
    {
        return $this->queryForMerchant($merchantId)
            ->whereKey($shipmentId)
            ->firstOrFail();
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'merchant',
            'order',
            'deliveryGovernorate',
            'deliveryArea',
            'assignedDriver',
            'histories',
            'deliveryAttempts',
        ];
    }
}
