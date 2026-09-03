<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table): void {
            $table->foreignId('governorate_id')->nullable()->after('address')->constrained('governorates')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->after('governorate_id')->constrained('areas')->nullOnDelete();
            $table->string('registration_source', 50)->nullable()->after('status')->index();
            $table->foreignId('reviewed_by')->nullable()->after('registration_source')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('rejection_reason')->nullable()->after('reviewed_at');
            $table->text('approval_notes')->nullable()->after('rejection_reason');

            $table->index(['registration_source', 'status', 'created_at'], 'merchants_registration_queue_index');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table): void {
            $table->dropIndex('merchants_registration_queue_index');
            $table->dropForeign(['governorate_id']);
            $table->dropForeign(['area_id']);
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn([
                'governorate_id',
                'area_id',
                'registration_source',
                'reviewed_by',
                'reviewed_at',
                'rejection_reason',
                'approval_notes',
            ]);
        });
    }
};
