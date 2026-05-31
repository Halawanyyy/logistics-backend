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
            $table->string('warehouse_fulfillment_status')
                ->default('not_required')
                ->after('fulfillment_type');

            $table->index('warehouse_fulfillment_status');
        });

        DB::table('orders')
            ->where('fulfillment_type', 'from_warehouse')
            ->update(['warehouse_fulfillment_status' => 'pending_reservation']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['warehouse_fulfillment_status']);
            $table->dropColumn('warehouse_fulfillment_status');
        });
    }
};
