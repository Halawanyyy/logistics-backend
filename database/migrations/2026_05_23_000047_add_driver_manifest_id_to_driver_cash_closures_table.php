<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_cash_closures', function (Blueprint $table) {
            $table->foreignId('driver_manifest_id')
                ->nullable()
                ->after('driver_id')
                ->constrained('driver_manifests')
                ->nullOnDelete();

            $table->unique('driver_manifest_id');
        });
    }

    public function down(): void
    {
        Schema::table('driver_cash_closures', function (Blueprint $table) {
            $table->dropUnique(['driver_manifest_id']);
            $table->dropConstrainedForeignId('driver_manifest_id');
        });
    }
};
