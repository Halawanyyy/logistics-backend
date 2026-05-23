<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'code']);
            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('warehouse_shelves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('warehouse_zone_id')->nullable()->constrained('warehouse_zones')->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'code']);
            $table->index(['warehouse_id', 'status']);
            $table->index('warehouse_zone_id');
        });

        Schema::create('warehouse_bins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('warehouse_zone_id')->nullable()->constrained('warehouse_zones')->nullOnDelete();
            $table->foreignId('warehouse_shelf_id')->nullable()->constrained('warehouse_shelves')->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('barcode')->nullable()->unique();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'code']);
            $table->index(['warehouse_id', 'status']);
            $table->index('warehouse_zone_id');
            $table->index('warehouse_shelf_id');
        });

        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->foreignId('warehouse_bin_id')
                ->nullable()
                ->after('warehouse_product_id')
                ->constrained('warehouse_bins')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_bin_id');
        });

        Schema::dropIfExists('warehouse_bins');
        Schema::dropIfExists('warehouse_shelves');
        Schema::dropIfExists('warehouse_zones');
    }
};
