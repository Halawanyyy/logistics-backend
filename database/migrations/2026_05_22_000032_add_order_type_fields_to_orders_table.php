<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_type')->default('delivery')->after('shipping_fee');
            $table->foreignId('original_order_id')->nullable()->after('order_type')->constrained('orders')->nullOnDelete();
            $table->text('swap_notes')->nullable()->after('notes');

            $table->index('order_type');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['order_type']);
            $table->dropConstrainedForeignId('original_order_id');
            $table->dropColumn(['order_type', 'swap_notes']);
        });
    }
};
