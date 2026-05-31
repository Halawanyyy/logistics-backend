<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_portal_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete()->unique();
            $table->string('default_fulfillment_type')->nullable();
            $table->string('default_payment_type')->nullable();
            $table->text('default_pickup_address')->nullable();
            $table->string('default_label_format')->nullable();
            $table->json('notification_preferences')->nullable();
            $table->json('integration_preferences')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_portal_settings');
    }
};
