<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedBigInteger('current_value')->default(0);
            $table->string('prefix', 50);
            $table->unsignedInteger('padding')->default(6);
            $table->timestamps();
        });

        Schema::create('waybill_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_number')->unique();
            $table->unsignedInteger('quantity');
            $table->string('first_waybill_number');
            $table->string('last_waybill_number');
            $table->string('label_template')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['generated_at', 'generated_by']);
        });

        Schema::create('waybills', function (Blueprint $table): void {
            $table->id();
            $table->string('waybill_number')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('status', 30)->index();
            $table->string('source', 30)->index();
            $table->foreignId('batch_id')->nullable()->constrained('waybill_batches')->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->unique('shipment_id');
            $table->index(['batch_id', 'status']);
            $table->index(['merchant_id', 'status']);
        });

        $initialWaybillValue = $this->highestCompatibleHistoricalValue('WB-');
        $now = now();

        DB::table('number_sequences')->insert([
            [
                'key' => 'waybill',
                'current_value' => $initialWaybillValue,
                'prefix' => 'WB-',
                'padding' => 6,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'waybill_batch',
                'current_value' => 0,
                'prefix' => 'WBB-',
                'padding' => 6,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('waybills');
        Schema::dropIfExists('waybill_batches');
        Schema::dropIfExists('number_sequences');
    }

    private function highestCompatibleHistoricalValue(string $prefix): int
    {
        $highest = 0;
        $targets = [
            ['orders', 'order_number'],
            ['orders', 'barcode'],
            ['shipments', 'shipment_number'],
            ['shipments', 'barcode'],
        ];

        foreach ($targets as [$table, $column]) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->select(['id', $column])
                ->where($column, 'like', $prefix.'%')
                ->chunkById(1000, function ($rows) use (&$highest, $column, $prefix): void {
                    foreach ($rows as $row) {
                        $value = $row->{$column};

                        if ($value !== null && preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', $value, $matches) === 1) {
                            $highest = max($highest, (int) $matches[1]);
                        }
                    }
                });
        }

        return $highest;
    }
};
