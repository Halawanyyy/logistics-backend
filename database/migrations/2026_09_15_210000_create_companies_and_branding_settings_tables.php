<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug');
                $table->string('storage_namespace')->unique();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'company_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('branding_settings')) {
            Schema::create('branding_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();
                $table->string('logo_path')->nullable();
                $table->string('logo_disk')->default('public');
                $table->string('app_name')->nullable();
                $table->string('legacy_logo_url', 2048)->nullable();
                $table->timestamps();
            });
        }

        // Safe and idempotent backfill for default company
        $defaultCompanyName = 'Fast Logistics';
        $legacyLogo = null;

        if (Schema::hasTable('system_settings')) {
            $nameSetting = DB::table('system_settings')->where('key', 'company_name')->first();
            if ($nameSetting && ! empty($nameSetting->value)) {
                $defaultCompanyName = trim((string) $nameSetting->value);
            }

            $logoSetting = DB::table('system_settings')->where('key', 'company_logo')->first();
            if ($logoSetting && ! empty($logoSetting->value)) {
                $legacyLogo = trim((string) $logoSetting->value);
            }
        }

        $existingCompany = DB::table('companies')->first();
        if (! $existingCompany) {
            $slug = Str::slug($defaultCompanyName);
            if ($slug === '') {
                $slug = 'fast-logistics';
            }

            $companyId = DB::table('companies')->insertGetId([
                'name' => $defaultCompanyName,
                'slug' => $slug,
                'storage_namespace' => 'temp-placeholder',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $storageNamespace = sprintf('company-%s--%s', $companyId, $slug);
            DB::table('companies')->where('id', $companyId)->update([
                'storage_namespace' => $storageNamespace,
            ]);

            $existingCompany = DB::table('companies')->where('id', $companyId)->first();
        }

        if ($existingCompany && Schema::hasTable('branding_settings')) {
            $existingBranding = DB::table('branding_settings')->where('company_id', $existingCompany->id)->first();
            if (! $existingBranding) {
                DB::table('branding_settings')->insert([
                    'company_id' => $existingCompany->id,
                    'logo_path' => null,
                    'logo_disk' => 'public',
                    'app_name' => $existingCompany->name,
                    'legacy_logo_url' => $legacyLogo,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (Schema::hasTable('users') && Schema::hasColumn('users', 'company_id')) {
                DB::table('users')->whereNull('company_id')->update([
                    'company_id' => $existingCompany->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_settings');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'company_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_id');
            });
        }

        Schema::dropIfExists('companies');
    }
};
