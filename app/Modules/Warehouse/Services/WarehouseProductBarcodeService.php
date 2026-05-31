<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Warehouse\Models\WarehouseProduct;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\BarcodeService;

class WarehouseProductBarcodeService
{
    public function __construct(
        private readonly BarcodeService $barcodeService,
        private readonly ViewFactory $viewFactory,
    ) {
    }

    public function ensureBarcode(WarehouseProduct $warehouseProduct): WarehouseProduct
    {
        return DB::transaction(function () use ($warehouseProduct) {
            $product = WarehouseProduct::query()
                ->lockForUpdate()
                ->findOrFail($warehouseProduct->id);

            if ($product->barcode !== null && $product->barcode !== '') {
                return $product->fresh('merchant');
            }

            $product->update([
                'barcode' => $this->barcodeService->generateWarehouseProductBarcode((int) $product->merchant_id),
                'barcode_type' => $product->barcode_type ?? 'code128',
                'barcode_generated_at' => now(),
            ]);

            return $product->fresh('merchant');
        });
    }

    public function renderSingleLabel(WarehouseProduct $warehouseProduct): string
    {
        return $this->renderLabels(collect([[
            'warehouse_product' => $warehouseProduct,
            'quantity' => 1,
            'warehouse_name' => null,
        ]]));
    }

    /**
     * @param  Collection<int, array{warehouse_product:WarehouseProduct,quantity:int,warehouse_name:?string}>  $labels
     */
    public function renderLabels(Collection $labels): string
    {
        return $this->viewFactory->make('warehouse.barcode-labels', [
            'labels' => $labels,
        ])->render();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function buildBulkLabels(array $items): string
    {
        $productIds = collect($items)->pluck('warehouse_product_id')->unique()->values();
        $products = WarehouseProduct::query()
            ->with('merchant')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $labels = collect();

        foreach ($items as $item) {
            $product = $products->get((int) $item['warehouse_product_id']);

            if (! $product instanceof WarehouseProduct) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Warehouse product not found.',
                ], 404));
            }

            $product = $this->ensureBarcode($product);

            for ($index = 0; $index < (int) $item['quantity']; $index++) {
                $labels->push([
                    'warehouse_product' => $product,
                    'quantity' => 1,
                    'warehouse_name' => $item['warehouse_name'] ?? null,
                ]);
            }
        }

        return $this->renderLabels($labels);
    }
}
