<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('merchant_inbound_stock_requests')) {
            Schema::create('merchant_inbound_stock_requests', function (Blueprint $table): void {
                $table->id();
                $table->string('request_number')->unique();
                $table->unsignedBigInteger('merchant_id');
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->string('status');
                $table->unsignedBigInteger('requested_by');
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->unsignedBigInteger('linked_warehouse_receipt_id')->nullable();
                $table->date('expected_arrival_date')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->text('notes')->nullable();
                $table->text('internal_notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Explicit short foreign key names
                $table->foreign('merchant_id', 'misr_merchant_fk')
                    ->references('id')
                    ->on('merchants')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                $table->foreign('warehouse_id', 'misr_warehouse_fk')
                    ->references('id')
                    ->on('warehouses')
                    ->nullOnDelete();

                $table->foreign('requested_by', 'misr_requested_by_fk')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                $table->foreign('reviewed_by', 'misr_reviewed_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign('linked_warehouse_receipt_id', 'misr_receipt_fk')
                    ->references('id')
                    ->on('warehouse_receipts')
                    ->nullOnDelete();

                // Explicit short index names
                $table->index(['merchant_id', 'status'], 'misr_merchant_status_idx');
                $table->index(['warehouse_id', 'status'], 'misr_warehouse_status_idx');
            });
        } else {
            Schema::table('merchant_inbound_stock_requests', function (Blueprint $table): void {
                if (!Schema::hasColumn('merchant_inbound_stock_requests', 'id')) {
                    $table->id();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_requests', 'request_number')) {
                    $table->string('request_number')->unique();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_requests', 'merchant_id')) {
                    $table->unsignedBigInteger('merchant_id');
                    $table->foreign('merchant_id', 'misr_merchant_fk')
                        ->references('id')
                        ->on('merchants')
                        ->cascadeOnUpdate()
                        ->restrictOnDelete();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_requests', 'warehouse_id')) {
                    $table->unsignedBigInteger('warehouse_id')->nullable();
                    $table->foreign('warehouse_id', 'misr_warehouse_fk')
                        ->references('id')
                        ->on('warehouses')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_requests', 'status')) {
                    $table->string('status');
                }
                if (!Schema::hasColumn('merchant_inbound_stock_requests', 'requested_by')) {
                    $table->unsignedBigInteger('requested_by');
                    $table->foreign('requested_by', 'misr_requested_by_fk')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnUpdate()
                        ->restrictOnDelete();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_requests', 'reviewed_by')) {
                    $table->unsignedBigInteger('reviewed_by')->nullable();
                    $table->foreign('reviewed_by', 'misr_reviewed_by_fk')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_requests', 'linked_warehouse_receipt_id')) {
                    $table->unsignedBigInteger('linked_warehouse_receipt_id')->nullable();
                    $table->foreign('linked_warehouse_receipt_id', 'misr_receipt_fk')
                        ->references('id')
                        ->on('warehouse_receipts')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_requests', 'expected_arrival_date')) {
                    $table->date('expected_arrival_date')->nullable();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_requests', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_requests', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_requests', 'received_at')) {
                    $table->timestamp('received_at')->nullable();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_requests', 'closed_at')) {
                    $table->timestamp('closed_at')->nullable();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_requests', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_requests', 'internal_notes')) {
                    $table->text('internal_notes')->nullable();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_requests', 'created_at')) {
                    $table->timestamps();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_requests', 'deleted_at')) {
                    $table->softDeletes();
                }

                // Check and add indexes
                $indexes = Schema::getIndexes('merchant_inbound_stock_requests');
                $hasMerchantStatus = false;
                $hasWarehouseStatus = false;
                foreach ($indexes as $index) {
                    if (isset($index['columns'])) {
                        if ($index['columns'] === ['merchant_id', 'status']) {
                            $hasMerchantStatus = true;
                        }
                        if ($index['columns'] === ['warehouse_id', 'status']) {
                            $hasWarehouseStatus = true;
                        }
                    }
                    if (isset($index['name'])) {
                        if ($index['name'] === 'misr_merchant_status_idx' || $index['name'] === 'merchant_inbound_stock_requests_merchant_id_status_index') {
                            $hasMerchantStatus = true;
                        }
                        if ($index['name'] === 'misr_warehouse_status_idx' || $index['name'] === 'merchant_inbound_stock_requests_warehouse_id_status_index') {
                            $hasWarehouseStatus = true;
                        }
                    }
                }
                if (!$hasMerchantStatus) {
                    $table->index(['merchant_id', 'status'], 'misr_merchant_status_idx');
                }
                if (!$hasWarehouseStatus) {
                    $table->index(['warehouse_id', 'status'], 'misr_warehouse_status_idx');
                }
            });
        }

        if (!Schema::hasTable('merchant_inbound_stock_request_items')) {
            Schema::create('merchant_inbound_stock_request_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('inbound_request_id');
                $table->unsignedBigInteger('warehouse_product_id')->nullable();
                $table->string('requested_product_name')->nullable();
                $table->string('requested_sku')->nullable();
                $table->unsignedInteger('quantity');
                $table->text('notes')->nullable();
                $table->timestamps();

                // Explicit short foreign key names
                $table->foreign('inbound_request_id', 'misri_request_fk')
                    ->references('id')
                    ->on('merchant_inbound_stock_requests')
                    ->cascadeOnDelete();

                $table->foreign('warehouse_product_id', 'misri_wp_fk')
                    ->references('id')
                    ->on('warehouse_products')
                    ->nullOnDelete();

                // Explicit short index name
                $table->index(['warehouse_product_id'], 'misri_wp_idx');
            });
        } else {
            Schema::table('merchant_inbound_stock_request_items', function (Blueprint $table): void {
                if (!Schema::hasColumn('merchant_inbound_stock_request_items', 'id')) {
                    $table->id();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_request_items', 'inbound_request_id')) {
                    $table->unsignedBigInteger('inbound_request_id');
                    $table->foreign('inbound_request_id', 'misri_request_fk')
                        ->references('id')
                        ->on('merchant_inbound_stock_requests')
                        ->cascadeOnDelete();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_request_items', 'warehouse_product_id')) {
                    $table->unsignedBigInteger('warehouse_product_id')->nullable();
                    $table->foreign('warehouse_product_id', 'misri_wp_fk')
                        ->references('id')
                        ->on('warehouse_products')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_request_items', 'requested_product_name')) {
                    $table->string('requested_product_name')->nullable();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_request_items', 'requested_sku')) {
                    $table->string('requested_sku')->nullable();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_request_items', 'quantity')) {
                    $table->unsignedInteger('quantity');
                }
                if (!Schema::hasColumn('merchant_inbound_stock_request_items', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (!Schema::hasColumn('merchant_inbound_stock_request_items', 'created_at')) {
                    $table->timestamps();
                }

                // Check and add indexes
                $indexes = Schema::getIndexes('merchant_inbound_stock_request_items');
                $hasWarehouseProductIndex = false;
                foreach ($indexes as $index) {
                    if (isset($index['columns']) && $index['columns'] === ['warehouse_product_id']) {
                        $hasWarehouseProductIndex = true;
                    }
                    if (isset($index['name'])) {
                        if ($index['name'] === 'misri_wp_idx' || $index['name'] === 'merchant_inbound_stock_request_items_warehouse_product_id_index') {
                            $hasWarehouseProductIndex = true;
                        }
                    }
                }
                if (!$hasWarehouseProductIndex) {
                    $table->index(['warehouse_product_id'], 'misri_wp_idx');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_inbound_stock_request_items');
        Schema::dropIfExists('merchant_inbound_stock_requests');
    }
};
