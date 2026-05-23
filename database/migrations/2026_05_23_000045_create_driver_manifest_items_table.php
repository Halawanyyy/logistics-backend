<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_manifest_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_manifest_id')->constrained('driver_manifests')->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('shipment_number');
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->text('delivery_address');
            $table->decimal('cod_amount', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->string('shipment_status_at_loadout')->nullable();
            $table->string('final_status')->nullable();
            $table->decimal('delivered_cod_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('driver_manifest_id');
            $table->index('shipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_manifest_items');
    }
};
