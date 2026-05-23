<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_manifests', function (Blueprint $table) {
            $table->id();
            $table->string('manifest_number')->unique();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->date('manifest_date');
            $table->string('status')->default('open');
            $table->unsignedInteger('total_shipments')->default(0);
            $table->decimal('expected_cod_amount', 12, 2)->default(0);
            $table->decimal('actual_received_amount', 12, 2)->nullable();
            $table->decimal('difference_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('driver_id');
            $table->index('manifest_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_manifests');
    }
};
