<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_plan_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_plan_id')->constrained('pricing_plans')->cascadeOnDelete();
            $table->foreignId('governorate_id')->constrained('governorates')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->decimal('delivery_fee', 10, 2);
            $table->decimal('swap_fee', 10, 2)->default(0);
            $table->decimal('return_fee', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('pricing_plan_id');
            $table->index('governorate_id');
            $table->index('area_id');
            $table->index(['pricing_plan_id', 'governorate_id', 'area_id'], 'pricing_plan_rates_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_plan_rates');
    }
};
