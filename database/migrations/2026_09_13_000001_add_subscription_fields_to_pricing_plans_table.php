<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->decimal('monthly_price', 10, 2)->nullable()->after('monthly_orders_to');
            $table->decimal('yearly_price', 10, 2)->nullable()->after('monthly_price');
            $table->string('currency', 3)->default('EGP')->after('yearly_price');
            $table->json('features')->nullable()->after('currency');
            $table->boolean('is_popular')->default(false)->after('features');
            $table->boolean('is_custom_pricing')->default(false)->after('is_popular');
            $table->integer('display_order')->default(0)->after('is_custom_pricing');
            $table->boolean('is_visible_to_merchants')->default(true)->after('display_order');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->dropColumn([
                'monthly_price',
                'yearly_price',
                'currency',
                'features',
                'is_popular',
                'is_custom_pricing',
                'display_order',
                'is_visible_to_merchants',
            ]);
        });
    }
};
