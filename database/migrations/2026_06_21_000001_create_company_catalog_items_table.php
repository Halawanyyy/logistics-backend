<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('category')->nullable();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->text('description')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('currency')->default('EGP');
            $table->string('size_label')->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->string('unit')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_visible_to_merchants')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('category');
            $table->index('status');
            $table->index('is_visible_to_merchants');
            $table->index(['type', 'status', 'is_visible_to_merchants']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_catalog_items');
    }
};
