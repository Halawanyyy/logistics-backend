<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_returns', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('quantity');
            $table->foreignId('received_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('inspected_by')->nullable()->after('received_by')->constrained('users')->nullOnDelete();
            $table->foreignId('restocked_by')->nullable()->after('inspected_by')->constrained('users')->nullOnDelete();
            $table->foreignId('damaged_by')->nullable()->after('restocked_by')->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->after('damaged_by')->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->after('rejected_by')->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable()->after('created_at');
            $table->timestamp('inspected_at')->nullable()->after('received_at');
            $table->timestamp('restocked_at')->nullable()->after('inspected_at');
            $table->timestamp('damaged_at')->nullable()->after('restocked_at');
            $table->timestamp('rejected_at')->nullable()->after('damaged_at');
            $table->timestamp('closed_at')->nullable()->after('rejected_at');

            $table->index('status');
            $table->index('received_at');
            $table->index('inspected_at');
            $table->index('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_returns', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['received_at']);
            $table->dropIndex(['inspected_at']);
            $table->dropIndex(['closed_at']);
            $table->dropConstrainedForeignId('received_by');
            $table->dropConstrainedForeignId('inspected_by');
            $table->dropConstrainedForeignId('restocked_by');
            $table->dropConstrainedForeignId('damaged_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn([
                'status',
                'received_at',
                'inspected_at',
                'restocked_at',
                'damaged_at',
                'rejected_at',
                'closed_at',
            ]);
        });
    }
};
