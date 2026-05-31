<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_products', function (Blueprint $table) {
            $table->string('barcode_type')->nullable()->after('barcode');
            $table->timestamp('barcode_generated_at')->nullable()->after('barcode_type');
            $table->unsignedInteger('minimum_stock_quantity')->default(0)->after('status');
            $table->boolean('low_stock_alert_enabled')->default(false)->after('minimum_stock_quantity');

            $table->index('barcode_type');
            $table->index('minimum_stock_quantity');
            $table->index('low_stock_alert_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_products', function (Blueprint $table) {
            $table->dropIndex(['barcode_type']);
            $table->dropIndex(['minimum_stock_quantity']);
            $table->dropIndex(['low_stock_alert_enabled']);
            $table->dropColumn([
                'barcode_type',
                'barcode_generated_at',
                'minimum_stock_quantity',
                'low_stock_alert_enabled',
            ]);
        });
    }
};
