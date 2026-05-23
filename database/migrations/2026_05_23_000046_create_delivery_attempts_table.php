<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->timestamp('attempt_date');
            $table->string('result');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('next_action')->nullable();
            $table->timestamp('next_attempt_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('shipment_id');
            $table->index('driver_id');
            $table->index('result');
            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_attempts');
    }
};
