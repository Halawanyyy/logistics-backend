<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_receipt_id')->constrained('warehouse_receipts')->cascadeOnDelete();
            $table->foreignId('warehouse_product_id')->constrained('warehouse_products')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('accepted_quantity')->default(0);
            $table->unsignedInteger('rejected_quantity')->default(0);
            $table->string('condition')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('warehouse_receipt_id');
            $table->index('warehouse_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_receipt_items');
    }
};
