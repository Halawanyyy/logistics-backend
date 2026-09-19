<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_settings')) {
            $exists = DB::table('system_settings')->where('key', 'pricing_contact_email')->exists();
            if (! $exists) {
                DB::table('system_settings')->insert([
                    'group' => 'system',
                    'key' => 'pricing_contact_email',
                    'value' => null,
                    'type' => 'string',
                    'label' => 'Pricing Contact Email',
                    'description' => 'Contact email for custom pricing inquiries.',
                    'is_public' => true,
                    'is_encrypted' => false,
                    'is_editable' => true,
                    'validation_rules' => json_encode(['rules' => 'nullable|email|max:255']),
                    'sort_order' => 77,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->where('key', 'pricing_contact_email')->delete();
        }
    }
};
