<?php

namespace Database\Seeders;

use App\Modules\Settings\Services\SystemSettingService;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        app(SystemSettingService::class)->seedDefaults();
    }
}
