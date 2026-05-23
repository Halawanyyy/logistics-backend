<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('export_type');
            $table->string('format')->default('csv');
            $table->json('filters')->nullable();
            $table->string('status')->default('completed');
            $table->string('file_path')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('export_type');
            $table->index('format');
            $table->index('status');
            $table->index('generated_by');
            $table->index('generated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_logs');
    }
};
