<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_integration_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash')->unique();
            $table->string('token_prefix')->nullable();
            $table->string('provider')->nullable();
            $table->json('permissions')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('merchant_id');
            $table->index('provider');
            $table->index('status');
            $table->index('token_prefix');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_integration_tokens');
    }
};
