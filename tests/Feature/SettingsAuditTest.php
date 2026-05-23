<?php

namespace Tests\Feature;

use App\Modules\Settings\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class SettingsAuditTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
        $this->actingAsSuperAdmin();
    }

    public function test_setting_update_creates_audit_log(): void
    {
        $setting = SystemSetting::query()->where('key', 'company_name')->firstOrFail();

        $this->putJson('/api/settings/'.$setting->id, [
            'value' => 'Fast Logistics Pro',
        ])->assertOk()
            ->assertJsonPath('data.key', 'company_name')
            ->assertJsonPath('data.value', 'Fast Logistics Pro');

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'setting_updated',
            'auditable_type' => 'system_setting',
            'auditable_id' => $setting->id,
        ]);
    }

    public function test_public_settings_only_returns_public_rows(): void
    {
        $response = $this->getJson('/api/public/settings')
            ->assertOk();

        $keys = collect($response->json('data'))->pluck('key')->all();

        $this->assertContains('company_name', $keys);
        $this->assertNotContains('default_currency', $keys);
    }
}
