<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('delivery_status')->default('pending')->after('quantity');
            $table->unsignedInteger('delivered_quantity')->default(0)->after('delivery_status');
            $table->unsignedInteger('returned_quantity')->default(0)->after('delivered_quantity');
            $table->unsignedInteger('damaged_quantity')->default(0)->after('returned_quantity');

            $table->index('delivery_status');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['delivery_status']);
            $table->dropColumn([
                'delivery_status',
                'delivered_quantity',
                'returned_quantity',
                'damaged_quantity',
            ]);
        });
    }
};
