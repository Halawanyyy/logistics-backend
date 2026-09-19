<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pricing_plans') && ! Schema::hasColumn('pricing_plans', 'requires_contact')) {
            Schema::table('pricing_plans', function (Blueprint $table) {
                $table->boolean('requires_contact')->default(false)->after('is_custom_pricing');
            });

            if (Schema::hasColumn('pricing_plans', 'is_custom_pricing')) {
                DB::table('pricing_plans')->where('is_custom_pricing', true)->update(['requires_contact' => true]);
            }
        }

        if (! Schema::hasTable('pricing_plan_features')) {
            Schema::create('pricing_plan_features', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pricing_plan_id')->constrained('pricing_plans')->cascadeOnDelete();
                $table->string('label');
                $table->boolean('is_included')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['pricing_plan_id', 'sort_order']);
            });

            if (Schema::hasTable('pricing_plans') && Schema::hasColumn('pricing_plans', 'features')) {
                $plans = DB::table('pricing_plans')->whereNotNull('features')->get();
                foreach ($plans as $plan) {
                    $features = is_string($plan->features) ? json_decode($plan->features, true) : $plan->features;
                    if (is_array($features)) {
                        $sort = 0;
                        foreach ($features as $feature) {
                            if (is_string($feature) && trim($feature) !== '') {
                                DB::table('pricing_plan_features')->insert([
                                    'pricing_plan_id' => $plan->id,
                                    'label' => trim($feature),
                                    'is_included' => true,
                                    'sort_order' => $sort++,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            } elseif (is_array($feature) && ! empty($feature['label'])) {
                                DB::table('pricing_plan_features')->insert([
                                    'pricing_plan_id' => $plan->id,
                                    'label' => trim($feature['label']),
                                    'is_included' => (bool) ($feature['is_included'] ?? true),
                                    'sort_order' => (int) ($feature['sort_order'] ?? $sort++),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_plan_features');

        if (Schema::hasTable('pricing_plans') && Schema::hasColumn('pricing_plans', 'requires_contact')) {
            Schema::table('pricing_plans', function (Blueprint $table) {
                $table->dropColumn('requires_contact');
            });
        }
    }
};
