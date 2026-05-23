<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_cash_closures', function (Blueprint $table) {
            $table->foreignId('treasury_account_id')->nullable()->after('driver_manifest_id')->constrained('treasury_accounts')->nullOnDelete();
        });

        Schema::table('merchant_payouts', function (Blueprint $table) {
            $table->foreignId('treasury_account_id')->nullable()->after('merchant_id')->constrained('treasury_accounts')->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('treasury_account_id')->nullable()->after('amount')->constrained('treasury_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('driver_cash_closures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treasury_account_id');
        });

        Schema::table('merchant_payouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treasury_account_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treasury_account_id');
        });
    }
};
