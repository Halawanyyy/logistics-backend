<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('picking_lists', function (Blueprint $table) {
            $table->id();
            $table->string('picking_number')->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('picked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'status']);
            $table->index('assigned_to');
        });

        Schema::create('picking_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('picking_list_id')->constrained('picking_lists')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('warehouse_product_id')->constrained('warehouse_products')->cascadeOnDelete();
            $table->foreignId('warehouse_bin_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
            $table->unsignedInteger('quantity_required');
            $table->unsignedInteger('quantity_picked')->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index('picking_list_id');
            $table->index('order_id');
            $table->index('order_item_id');
            $table->index('warehouse_product_id');
            $table->index('warehouse_bin_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('picking_list_items');
        Schema::dropIfExists('picking_lists');
    }
};
