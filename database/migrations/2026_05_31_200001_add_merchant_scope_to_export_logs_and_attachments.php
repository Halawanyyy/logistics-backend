<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('export_logs', function (Blueprint $table): void {
            $table->foreignId('merchant_id')
                ->nullable()
                ->after('id')
                ->constrained('merchants')
                ->nullOnDelete();
            $table->index('merchant_id');
        });

        Schema::table('attachments', function (Blueprint $table): void {
            $table->foreignId('merchant_id')
                ->nullable()
                ->after('id')
                ->constrained('merchants')
                ->nullOnDelete();
            $table->boolean('merchant_uploaded')->default(false)->after('uploaded_by');
            $table->index('merchant_id');
            $table->index('merchant_uploaded');
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table): void {
            $table->dropIndex(['merchant_uploaded']);
            $table->dropIndex(['merchant_id']);
            $table->dropConstrainedForeignId('merchant_id');
            $table->dropColumn('merchant_uploaded');
        });

        Schema::table('export_logs', function (Blueprint $table): void {
            $table->dropIndex(['merchant_id']);
            $table->dropConstrainedForeignId('merchant_id');
        });
    }
};
