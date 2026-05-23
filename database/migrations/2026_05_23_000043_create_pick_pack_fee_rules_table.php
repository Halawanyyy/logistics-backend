<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pick_pack_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->foreignId('pricing_plan_id')->nullable()->constrained('pricing_plans')->nullOnDelete();
            $table->string('name');
            $table->decimal('pick_fee_first_item', 12, 2)->default(0);
            $table->decimal('pick_fee_additional_item', 12, 2)->default(0);
            $table->decimal('packing_fee_base', 12, 2)->default(0);
            $table->decimal('fragile_extra_fee', 12, 2)->default(0);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('merchant_id');
            $table->index('pricing_plan_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pick_pack_fee_rules');
    }
};
