<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\User;
use App\Modules\MerchantPortal\Enums\MerchantPortalRole;
use App\Modules\MerchantPortal\Enums\MerchantPortalUserStatus;
use App\Modules\MerchantPortal\Models\MerchantUser;
use App\Modules\RolePermission\Models\Role;
use Database\Seeders\MerchantPortalCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class MerchantPortalSyncCommandTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        // Make sure the merchant_portal role is seeded
        Role::query()->updateOrCreate(
            ['slug' => 'merchant_portal'],
            [
                'name' => 'Merchant Portal',
                'description' => 'Merchant-facing portal access without internal permissions.',
                'is_system' => true,
                'status' => 'active',
            ]
        );
    }

    public function test_seeder_creates_default_roles_and_demo_users(): void
    {
        $this->seed(MerchantPortalCapabilitiesSeeder::class);

        $demoEmails = [
            'merchant.owner@example.com',
            'merchant.staff@example.com',
            'merchant.accountant@example.com',
            'merchant.warehouse@example.com',
            'merchant.viewer@example.com',
        ];

        foreach ($demoEmails as $email) {
            $user = User::query()->where('email', $email)->first();
            $this->assertNotNull($user);
            $this->assertEquals('merchant_portal', $user->role);

            $membership = MerchantUser::query()->where('user_id', $user->id)->first();
            $this->assertNotNull($membership);
            $this->assertEquals(MerchantPortalUserStatus::ACTIVE, $membership->status);
        }
    }

    public function test_command_syncs_existing_memberships(): void
    {
        $merchant = $this->makeMerchant();
        $user = User::factory()->create(['role' => 'merchant_portal']);
        $membership = $this->makeMerchantPortalUser($merchant, $user, [
            'role' => MerchantPortalRole::STAFF,
        ]);

        $exitCode = Artisan::call('merchant-portal:sync-capabilities', [
            '--dry-run' => true,
        ]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Found 1 merchant portal membership records', $output);
        $this->assertStringContainsString('Role: staff', $output);
    }

    public function test_command_creates_missing_owners_for_active_merchants(): void
    {
        $merchant = $this->makeMerchant([
            'email' => 'active.merchant@example.com',
            'status' => 'active',
        ]);

        // Assert no owner membership exists initially
        $this->assertFalse(
            MerchantUser::query()
                ->where('merchant_id', $merchant->id)
                ->where('role', MerchantPortalRole::OWNER)
                ->exists()
        );

        $exitCode = Artisan::call('merchant-portal:sync-capabilities', [
            '--create-missing-owners' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        // Assert owner user & membership was created successfully
        $newUser = User::query()->where('email', 'active.merchant@example.com')->first();
        $this->assertNotNull($newUser);

        $membership = MerchantUser::query()
            ->where('merchant_id', $merchant->id)
            ->where('user_id', $newUser->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertEquals(MerchantPortalRole::OWNER, $membership->role);
        $this->assertTrue($membership->is_primary);
    }
}
