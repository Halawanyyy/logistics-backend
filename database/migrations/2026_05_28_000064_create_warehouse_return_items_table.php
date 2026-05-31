<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_return_id')->constrained('warehouse_returns')->cascadeOnDelete();
            $table->foreignId('warehouse_product_id')->constrained('warehouse_products')->cascadeOnDelete();
            $table->unsignedInteger('expected_quantity')->default(0);
            $table->unsignedInteger('received_quantity')->default(0);
            $table->unsignedInteger('restock_quantity')->default(0);
            $table->unsignedInteger('damaged_quantity')->default(0);
            $table->unsignedInteger('rejected_quantity')->default(0);
            $table->string('condition')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('warehouse_return_id');
            $table->index('warehouse_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_return_items');
    }
};
