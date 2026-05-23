<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->foreignId('pricing_plan_id')
                ->nullable()
                ->after('status')
                ->constrained('pricing_plans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pricing_plan_id');
        });
    }
};
