<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('barcode')->nullable()->after('order_number');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->string('barcode')->nullable()->after('shipment_number');
        });

        $this->backfillOrderBarcodes();
        $this->backfillShipmentBarcodes();
        $this->backfillWarehouseProductBarcodes();

        Schema::table('orders', function (Blueprint $table) {
            $table->unique('barcode');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->unique('barcode');
        });

        Schema::table('warehouse_products', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
            $table->unique('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_products', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->index('barcode');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->dropColumn('barcode');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->dropColumn('barcode');
        });
    }

    private function backfillOrderBarcodes(): void
    {
        $sequences = [];

        DB::table('orders')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->get()
            ->each(function ($order) use (&$sequences): void {
                $datePart = $this->datePart($order->created_at);
                $sequence = ($sequences[$datePart] ?? 0) + 1;
                $sequences[$datePart] = $sequence;

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'barcode' => sprintf('ORD-BC-%s-%06d', $datePart, $sequence),
                    ]);
            });
    }

    private function backfillShipmentBarcodes(): void
    {
        $sequences = [];

        DB::table('shipments')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->get()
            ->each(function ($shipment) use (&$sequences): void {
                $datePart = $this->datePart($shipment->created_at);
                $sequence = ($sequences[$datePart] ?? 0) + 1;
                $sequences[$datePart] = $sequence;

                DB::table('shipments')
                    ->where('id', $shipment->id)
                    ->update([
                        'barcode' => sprintf('SHP-BC-%s-%06d', $datePart, $sequence),
                    ]);
            });
    }

    private function backfillWarehouseProductBarcodes(): void
    {
        $sequences = [];
        $seenBarcodes = [];

        DB::table('warehouse_products')
            ->select(['id', 'merchant_id', 'barcode', 'created_at'])
            ->orderBy('id')
            ->get()
            ->each(function ($product) use (&$sequences, &$seenBarcodes): void {
                $existingBarcode = $product->barcode;

                if ($existingBarcode !== null && ! isset($seenBarcodes[$existingBarcode])) {
                    $seenBarcodes[$existingBarcode] = true;
                    return;
                }

                $datePart = $this->datePart($product->created_at);
                $sequenceKey = $product->merchant_id.'-'.$datePart;
                $sequence = ($sequences[$sequenceKey] ?? 0) + 1;
                $sequences[$sequenceKey] = $sequence;

                $barcode = sprintf('WH-PROD-%d-%s-%06d', $product->merchant_id, $datePart, $sequence);

                while (isset($seenBarcodes[$barcode])) {
                    $sequence++;
                    $sequences[$sequenceKey] = $sequence;
                    $barcode = sprintf('WH-PROD-%d-%s-%06d', $product->merchant_id, $datePart, $sequence);
                }

                DB::table('warehouse_products')
                    ->where('id', $product->id)
                    ->update(['barcode' => $barcode]);

                $seenBarcodes[$barcode] = true;
            });
    }

    private function datePart($value): string
    {
        return \Illuminate\Support\Carbon::parse($value ?? now())->format('Ymd');
    }
};
