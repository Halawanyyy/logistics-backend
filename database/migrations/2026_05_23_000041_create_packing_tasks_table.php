<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packing_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('packing_number')->unique();
            $table->foreignId('picking_list_id')->nullable()->constrained('picking_lists')->nullOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->decimal('package_weight', 10, 2)->nullable();
            $table->decimal('package_length', 10, 2)->nullable();
            $table->decimal('package_width', 10, 2)->nullable();
            $table->decimal('package_height', 10, 2)->nullable();
            $table->decimal('packaging_fee', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('packed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('packed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('picking_list_id');
            $table->index('order_id');
            $table->index('shipment_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_tasks');
    }
};
