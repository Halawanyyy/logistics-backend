<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_packaging_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('company_catalog_item_id')->nullable()->constrained('company_catalog_items')->nullOnDelete();
            $table->string('item_name');
            $table->string('item_sku')->nullable();
            $table->string('category')->nullable();
            $table->string('size_label')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('total_price', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('company_catalog_item_id');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_packaging_items');
    }
};
