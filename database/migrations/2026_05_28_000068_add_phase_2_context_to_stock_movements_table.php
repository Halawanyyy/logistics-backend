<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('warehouse_product_id')->constrained('orders')->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->after('order_id')->constrained('shipments')->nullOnDelete();

            $table->index('order_id');
            $table->index('shipment_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['shipment_id']);
            $table->dropConstrainedForeignId('order_id');
            $table->dropConstrainedForeignId('shipment_id');
        });
    }
};
