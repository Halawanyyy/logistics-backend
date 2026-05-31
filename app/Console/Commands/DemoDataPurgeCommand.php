<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Models\User;
use App\Support\DemoDataRegistry;
use App\Modules\Driver\Models\Driver;
use App\Modules\Finance\Models\MerchantInvoice;
use App\Modules\Finance\Models\MerchantPayout;
use App\Modules\Finance\Models\MerchantWalletTransaction;
use App\Modules\LocationPricing\Models\Area;
use App\Modules\LocationPricing\Models\Governorate;
use App\Modules\MerchantPortal\Models\MerchantPortalSetting;
use App\Modules\MerchantPortal\Models\MerchantUser;
use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Shipment\Models\ShipmentStatusHistory;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseBin;
use App\Modules\Warehouse\Models\WarehouseProduct;
use App\Modules\Warehouse\Models\WarehouseShelf;
use App\Modules\Warehouse\Models\WarehouseZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoDataPurgeCommand extends Command
{
    protected $signature = 'demo:purge {--force : Skip the confirmation prompt}';

    protected $description = 'Permanently remove only demo records created by DemoDataSeeder.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm(
            'This will permanently delete only records created by DemoDataSeeder. Continue?'
        )) {
            $this->info('Demo data purge cancelled.');

            return self::SUCCESS;
        }

        $counts = DB::transaction(fn (): array => $this->purgeDemoData());
        $totalDeleted = array_sum($counts);

        if ($totalDeleted === 0) {
            $this->info('No demo data records were found.');

            return self::SUCCESS;
        }

        $this->info('Demo data purge completed.');

        foreach ($counts as $entity => $count) {
            if ($count > 0) {
                $this->line(sprintf('- %s: %d', str_replace('_', ' ', $entity), $count));
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function purgeDemoData(): array
    {
        $merchantIds = Merchant::withTrashed()
            ->where('email', DemoDataRegistry::MERCHANT_EMAIL)
            ->pluck('id');

        $userIds = User::withTrashed()
            ->whereIn('email', DemoDataRegistry::userEmails())
            ->pluck('id');

        $warehouseIds = Warehouse::withTrashed()
            ->whereIn('code', DemoDataRegistry::warehouseCodes())
            ->pluck('id');

        $productIds = WarehouseProduct::withTrashed()
            ->where('sku', DemoDataRegistry::PRODUCT_SKU)
            ->pluck('id');

        $shipmentIds = Shipment::withTrashed()
            ->whereIn('shipment_number', DemoDataRegistry::shipmentNumbers())
            ->pluck('id');

        $orderIds = Order::withTrashed()
            ->whereIn('order_number', DemoDataRegistry::orderNumbers())
            ->pluck('id');

        $governorateIds = Governorate::query()
            ->where('name', DemoDataRegistry::GOVERNORATE_NAME)
            ->pluck('id');

        $counts = [];

        $counts['shipment_status_histories'] = $this->purgeDemoQuery(
            ShipmentStatusHistory::query()->whereIn('shipment_id', $shipmentIds)
        );

        $counts['stock_movements'] = $this->purgeDemoQuery(
            StockMovement::query()
            ->where(function ($query) use ($orderIds, $shipmentIds, $productIds, $warehouseIds): void {
                $query->where('reference_type', 'demo_seed')
                    ->where('notes', 'like', DemoDataRegistry::MARKER.'%')
                    ->orWhereIn('order_id', $orderIds)
                    ->orWhereIn('shipment_id', $shipmentIds)
                    ->orWhereIn('warehouse_product_id', $productIds)
                    ->orWhereIn('warehouse_id', $warehouseIds);
            })
        );

        $counts['inventory_stocks'] = $this->purgeDemoQuery(
            InventoryStock::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->orWhereIn('warehouse_product_id', $productIds)
        );

        $counts['merchant_portal_settings'] = $this->purgeDemoQuery(
            MerchantPortalSetting::query()->whereIn('merchant_id', $merchantIds)
        );

        $counts['merchant_users'] = $this->purgeDemoQuery(
            MerchantUser::withTrashed()
            ->whereIn('merchant_id', $merchantIds)
            ->orWhereIn('user_id', $userIds)
        );

        $counts['wallet_transactions'] = $this->purgeDemoQuery(
            MerchantWalletTransaction::withTrashed()
                ->where('transaction_number', DemoDataRegistry::WALLET_TRANSACTION_NUMBER)
        );

        $counts['merchant_invoices'] = $this->purgeDemoQuery(
            MerchantInvoice::withTrashed()
                ->where('invoice_number', DemoDataRegistry::INVOICE_NUMBER)
        );

        $counts['merchant_payouts'] = $this->purgeDemoQuery(
            MerchantPayout::withTrashed()
            ->where('reference_number', DemoDataRegistry::PAYOUT_REFERENCE)
            ->whereIn('merchant_id', $merchantIds)
        );

        $counts['shipments'] = $this->purgeDemoQuery(
            Shipment::withTrashed()->whereIn('shipment_number', DemoDataRegistry::shipmentNumbers())
        );

        $counts['orders'] = $this->purgeDemoQuery(
            Order::withTrashed()->whereIn('order_number', DemoDataRegistry::orderNumbers())
        );

        $counts['warehouse_bins'] = $this->purgeDemoQuery(
            WarehouseBin::withTrashed()
            ->where('code', DemoDataRegistry::WAREHOUSE_BIN_CODE)
            ->whereIn('warehouse_id', $warehouseIds)
        );

        $counts['warehouse_shelves'] = $this->purgeDemoQuery(
            WarehouseShelf::withTrashed()
            ->where('code', DemoDataRegistry::WAREHOUSE_SHELF_CODE)
            ->whereIn('warehouse_id', $warehouseIds)
        );

        $counts['warehouse_zones'] = $this->purgeDemoQuery(
            WarehouseZone::withTrashed()
            ->where('code', DemoDataRegistry::WAREHOUSE_ZONE_CODE)
            ->whereIn('warehouse_id', $warehouseIds)
        );

        $counts['warehouse_products'] = $this->purgeDemoQuery(
            WarehouseProduct::withTrashed()
            ->where('sku', DemoDataRegistry::PRODUCT_SKU)
            ->whereIn('merchant_id', $merchantIds)
        );

        $counts['drivers'] = $this->purgeDemoQuery(
            Driver::withTrashed()->where('phone', DemoDataRegistry::DRIVER_PHONE)
        );

        $counts['warehouses'] = $this->purgeDemoQuery(
            Warehouse::withTrashed()->whereIn('code', DemoDataRegistry::warehouseCodes())
        );

        $counts['merchants'] = $this->purgeDemoQuery(
            Merchant::withTrashed()->where('email', DemoDataRegistry::MERCHANT_EMAIL)
        );

        $counts['users'] = $this->purgeDemoQuery(
            User::withTrashed()->whereIn('email', DemoDataRegistry::userEmails())
        );

        $counts['areas'] = $this->purgeDemoQuery(
            Area::withTrashed()
            ->where('name', DemoDataRegistry::AREA_NAME)
            ->whereIn('governorate_id', $governorateIds)
        );

        $counts['governorates'] = $this->purgeDemoQuery(
            Governorate::withTrashed()->where('name', DemoDataRegistry::GOVERNORATE_NAME)
        );

        return $counts;
    }

    private function purgeDemoQuery(Builder $query): int
    {
        $records = $query->get();
        $count = $records->count();

        foreach ($records as $record) {
            if (in_array(SoftDeletes::class, class_uses_recursive($record), true)) {
                $record->forceDelete();

                continue;
            }

            $record->delete();
        }

        return $count;
    }
}
