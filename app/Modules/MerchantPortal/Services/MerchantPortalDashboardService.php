<?php

namespace App\Modules\MerchantPortal\Services;

use App\Models\Merchant;
use App\Modules\MerchantPortal\Enums\MerchantInboundStockRequestStatus;
use App\Modules\MerchantPortal\Models\MerchantUser;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\WarehouseProduct;
use Illuminate\Database\Eloquent\Builder;

class MerchantPortalDashboardService
{
    public function __construct(
        private readonly MerchantPortalFinanceService $merchantPortalFinanceService,
        private readonly MerchantPortalInboundStockRequestService $merchantPortalInboundStockRequestService,
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function summary(Merchant $merchant, MerchantUser $merchantUser, array $filters = []): array
    {
        $merchantId = $merchant->id;
        $ordersQuery = Order::query()
            ->where('merchant_id', $merchantId)
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['delivery_governorate_id']), fn (Builder $query) => $query->where('delivery_governorate_id', $filters['delivery_governorate_id']));
        $shipmentsQuery = Shipment::query()
            ->where('merchant_id', $merchantId)
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['delivery_governorate_id']), fn (Builder $query) => $query->where('delivery_governorate_id', $filters['delivery_governorate_id']));

        $warehouseSection = $this->merchantPortalAccessService->can($merchantUser, 'warehouse.view')
            ? $this->warehouseSummary($merchantId)
            : null;
        $financeSection = $this->merchantPortalAccessService->can($merchantUser, 'finance.view')
            ? $this->merchantPortalFinanceService->financeSummary($merchant, $filters)
            : null;

        return [
            'orders' => [
                'total_orders' => (clone $ordersQuery)->count(),
                'today_orders' => (clone $ordersQuery)->whereDate('created_at', now()->toDateString())->count(),
                'pending_orders' => (clone $ordersQuery)->where('status', OrderStatus::PENDING_REVIEW)->count(),
                'confirmed_orders' => (clone $ordersQuery)->where('status', OrderStatus::CONFIRMED)->count(),
                'delivered_orders' => (clone $ordersQuery)->where('status', OrderStatus::DELIVERED)->count(),
                'returned_orders' => (clone $ordersQuery)->where('status', OrderStatus::FAILED)->count(),
                'cancelled_orders' => (clone $ordersQuery)->where('status', OrderStatus::CANCELLED)->count(),
            ],
            'shipments' => [
                'total_shipments' => (clone $shipmentsQuery)->count(),
                'pending_pickup_shipments' => (clone $shipmentsQuery)->where('status', ShipmentStatus::PENDING_PICKUP)->count(),
                'in_transit_shipments' => (clone $shipmentsQuery)->where('status', ShipmentStatus::IN_TRANSIT)->count(),
                'out_for_delivery_shipments' => (clone $shipmentsQuery)->where('status', ShipmentStatus::OUT_FOR_DELIVERY)->count(),
                'delivered_shipments' => (clone $shipmentsQuery)->where('status', ShipmentStatus::DELIVERED)->count(),
                'failed_shipments' => (clone $shipmentsQuery)->where('status', ShipmentStatus::FAILED)->count(),
                'returned_shipments' => (clone $shipmentsQuery)->whereIn('status', [ShipmentStatus::RETURNED, ShipmentStatus::RETURNED_TO_WAREHOUSE, ShipmentStatus::RETURNED_TO_HUB])->count(),
            ],
            'warehouse' => $warehouseSection,
            'finance' => $financeSection,
            'latest_orders' => (clone $ordersQuery)->latest()->limit(5)->get()->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'customer_name' => $order->customer_name,
                'cod_amount' => $order->cod_amount,
                'created_at' => $order->created_at,
            ])->values(),
            'latest_shipments' => (clone $shipmentsQuery)->latest()->limit(5)->get()->map(fn (Shipment $shipment) => [
                'id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'status' => $shipment->status,
                'customer_name' => $shipment->customer_name,
                'cod_amount' => $shipment->cod_amount,
                'created_at' => $shipment->created_at,
            ])->values(),
            'latest_wallet_transactions' => $financeSection === null
                ? []
                : $this->merchantPortalFinanceService->walletTransactionQueryForMerchant($merchantId)->limit(5)->get()->map(fn ($transaction) => [
                    'id' => $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                    'type' => $transaction->transaction_type,
                    'direction' => $transaction->direction,
                    'amount' => $transaction->amount,
                    'created_at' => $transaction->created_at,
                ])->values(),
            'latest_inbound_requests' => $warehouseSection === null
                ? []
                : $this->merchantPortalInboundStockRequestService->queryForMerchant($merchantId)->limit(5)->get()->map(fn ($request) => [
                    'id' => $request->id,
                    'request_number' => $request->request_number,
                    'status' => $request->status,
                    'expected_arrival_date' => $request->expected_arrival_date,
                    'created_at' => $request->created_at,
                ])->values(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function warehouseSummary(int $merchantId): array
    {
        $inventoryQuery = InventoryStock::query()
            ->whereHas('warehouseProduct', fn (Builder $query) => $query->where('merchant_id', $merchantId));

        return [
            'warehouse_products_count' => WarehouseProduct::query()->where('merchant_id', $merchantId)->count(),
            'low_stock_count' => WarehouseProduct::query()
                ->where('merchant_id', $merchantId)
                ->where('low_stock_alert_enabled', true)
                ->whereHas('inventoryStocks', fn (Builder $query) => $query->whereRaw('inventory_stocks.quantity_available <= warehouse_products.minimum_stock_quantity'))
                ->count(),
            'total_available_quantity' => (int) (clone $inventoryQuery)->sum('quantity_available'),
            'total_reserved_quantity' => (int) (clone $inventoryQuery)->sum('quantity_reserved'),
            'inbound_requests_pending_count' => $this->merchantPortalInboundStockRequestService->queryForMerchant($merchantId, [
                'status' => MerchantInboundStockRequestStatus::SUBMITTED,
            ])->count(),
        ];
    }
}
