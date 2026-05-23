<?php

namespace App\Services;

use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Settings\Services\SystemSettingService;
use App\Modules\Warehouse\Models\WarehouseBin;
use App\Modules\Warehouse\Models\WarehouseProduct;
use Illuminate\Database\Eloquent\Model;

class BarcodeService
{
    public function __construct(
        private readonly SystemSettingService $systemSettingService,
    ) {
    }

    public function generateOrderBarcode(): string
    {
        return $this->generateSequentialBarcode(
            model: new Order(),
            column: 'barcode',
            prefix: $this->dateBasedPrefix(
                (string) $this->systemSettingService->get('order_barcode_prefix', 'ORD-BC')
            ),
        );
    }

    public function generateShipmentBarcode(): string
    {
        return $this->generateSequentialBarcode(
            model: new Shipment(),
            column: 'barcode',
            prefix: $this->dateBasedPrefix(
                (string) $this->systemSettingService->get('shipment_barcode_prefix', 'SHP-BC')
            ),
        );
    }

    public function generateWarehouseProductBarcode(int $merchantId): string
    {
        return $this->generateSequentialBarcode(
            model: new WarehouseProduct(),
            column: 'barcode',
            prefix: sprintf(
                '%s-%d-%s-',
                (string) $this->systemSettingService->get('warehouse_product_barcode_prefix', 'WH-PROD'),
                $merchantId,
                $this->barcodeDatePart(),
            ),
        );
    }

    public function generateWarehouseBinBarcode(): string
    {
        return $this->generateSequentialBarcode(
            model: new WarehouseBin(),
            column: 'barcode',
            prefix: $this->dateBasedPrefix(
                (string) $this->systemSettingService->get('warehouse_bin_barcode_prefix', 'BIN-BC')
            ),
        );
    }

    public function isUnique(string $barcode, ?string $ignoreType = null, ?int $ignoreId = null): bool
    {
        foreach ($this->uniqueTargets() as $type => [$modelClass, $column]) {
            if ($ignoreType === $type && $ignoreId !== null) {
                $exists = $modelClass::query()
                    ->where($column, $barcode)
                    ->whereKeyNot($ignoreId)
                    ->exists();
            } else {
                $exists = $modelClass::query()->where($column, $barcode)->exists();
            }

            if ($exists) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{type:string,model:Model}|null
     */
    public function lookup(string $barcode): ?array
    {
        foreach ($this->lookupTargets() as $type => [$modelClass, $column]) {
            /** @var Model|null $model */
            $model = $modelClass::query()->where($column, $barcode)->first();

            if ($model !== null) {
                return [
                    'type' => $type,
                    'model' => $model,
                ];
            }
        }

        return null;
    }

    /**
     * @return array<string, array{0: class-string<Model>, 1: string}>
     */
    private function lookupTargets(): array
    {
        return [
            'order' => [Order::class, 'barcode'],
            'shipment' => [Shipment::class, 'barcode'],
            'warehouse_product' => [WarehouseProduct::class, 'barcode'],
        ];
    }

    /**
     * @return array<string, array{0: class-string<Model>, 1: string}>
     */
    private function uniqueTargets(): array
    {
        return [
            ...$this->lookupTargets(),
            'warehouse_bin' => [WarehouseBin::class, 'barcode'],
        ];
    }

    /**
     * @param  Model  $model
     */
    private function generateSequentialBarcode(Model $model, string $column, string $prefix): string
    {
        $latestBarcode = $model::query()
            ->withTrashed()
            ->where($column, 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc($column)
            ->value($column);

        $nextSequence = $latestBarcode === null
            ? 1
            : ((int) substr((string) $latestBarcode, -6)) + 1;

        $barcode = $prefix.str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);

        while (! $this->isUnique($barcode)) {
            $nextSequence++;
            $barcode = $prefix.str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
        }

        return $barcode;
    }

    private function dateBasedPrefix(string $basePrefix): string
    {
        return sprintf('%s-%s-', $basePrefix, $this->barcodeDatePart());
    }

    private function barcodeDatePart(): string
    {
        $format = (string) $this->systemSettingService->get('barcode_date_format', 'Ymd');

        return now()->format($format !== '' ? $format : 'Ymd');
    }
}
