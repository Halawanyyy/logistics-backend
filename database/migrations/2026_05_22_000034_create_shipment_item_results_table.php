<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_item_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('warehouse_product_id')->nullable()->constrained('warehouse_products')->nullOnDelete();
            $table->string('result_status');
            $table->unsignedInteger('quantity');
            $table->string('return_condition')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('shipment_id');
            $table->index('order_item_id');
            $table->index('result_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_item_results');
    }
};
