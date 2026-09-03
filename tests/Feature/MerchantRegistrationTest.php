<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\MerchantPortal\Enums\MerchantPortalRole;
use App\Modules\MerchantPortal\Enums\MerchantPortalUserStatus;
use App\Modules\MerchantPortal\Models\MerchantNotification;
use App\Modules\MerchantPortal\Models\MerchantUser;
use App\Modules\RolePermission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class MerchantRegistrationTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_merchant_can_self_register_transactionally_with_pending_owner_and_hashed_password(): void
    {
        $this->assertDatabaseHas('permissions', ['slug' => 'merchant_registration.view']);
        $this->assertDatabaseHas('permissions', ['slug' => 'merchant_registration.approve']);
        $this->assertDatabaseHas('permissions', ['slug' => 'merchant_registration.reject']);

        [$governorate, $area] = $this->makeLocation();

        $response = $this->postJson('/api/merchant-portal/register', $this->registrationPayload([
            'governorate_id' => $governorate->id,
            'area_id' => $area->id,
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.company_name', 'ABC Store')
            ->assertJsonPath('data.status', 'pending');

        $merchant = Merchant::query()->where('email', 'merchant@example.com')->firstOrFail();
        $owner = MerchantUser::query()->with('user')->where('merchant_id', $merchant->id)->firstOrFail();

        $this->assertSame('ABC Store', $merchant->name);
        $this->assertSame('pending', $merchant->status);
        $this->assertSame('self_registration', $merchant->registration_source);
        $this->assertNull($merchant->pricing_plan_id);
        $this->assertSame($governorate->id, $merchant->governorate_id);
        $this->assertSame($area->id, $merchant->area_id);
        $this->assertSame(MerchantPortalRole::OWNER, $owner->role);
        $this->assertSame(MerchantPortalUserStatus::ACTIVE, $owner->status);
        $this->assertTrue($owner->is_primary);
        $this->assertSame('Ahmed Mohamed', $owner->user->name);
        $this->assertTrue(Hash::check('password123', $owner->user->password));
        $this->assertNotSame('password123', $owner->user->password);
        $this->assertDatabaseHas('merchant_notifications', [
            'merchant_id' => $merchant->id,
            'type' => 'merchant_registration_submitted',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $merchant->id,
            'event_type' => 'merchant_registered',
        ]);
    }

    public function test_duplicate_email_and_invalid_payload_are_rejected(): void
    {
        User::factory()->create(['email' => 'merchant@example.com']);

        $this->postJson('/api/merchant-portal/register', $this->registrationPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/merchant-portal/register', [
            'company_name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['company_name', 'owner_name', 'email', 'phone', 'password']);

        $this->assertDatabaseCount('merchants', 0);
        $this->assertDatabaseCount('merchant_users', 0);
    }

    public function test_area_must_belong_to_selected_governorate(): void
    {
        [$governorate] = $this->makeLocation('Cairo', 'Nasr City');
        [, $otherArea] = $this->makeLocation('Giza', 'Dokki');

        $this->postJson('/api/merchant-portal/register', $this->registrationPayload([
            'governorate_id' => $governorate->id,
            'area_id' => $otherArea->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('area_id');
    }

    public function test_public_registration_prohibits_admin_only_fields(): void
    {
        $this->postJson('/api/merchant-portal/register', $this->registrationPayload([
            'status' => 'active',
            'pricing_plan_id' => 1,
            'role' => 'super_admin',
            'permissions' => ['*'],
            'wallet_balance' => 100000,
            'credit_limit' => 100000,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'pricing_plan_id',
                'role',
                'permissions',
                'wallet_balance',
                'credit_limit',
            ]);

        $this->assertDatabaseCount('merchants', 0);
    }

    public function test_pending_merchant_can_login_and_read_status_but_operational_routes_are_blocked(): void
    {
        $merchant = $this->registerMerchant();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'merchant@example.com',
            'password' => 'password123',
        ])->assertOk();

        $headers = ['Authorization' => 'Bearer '.$login->json('token')];

        $this->withHeaders($headers)->getJson('/api/merchant-portal/me')
            ->assertOk()
            ->assertJsonPath('data.merchant.id', $merchant->id)
            ->assertJsonPath('data.merchant.status', 'pending')
            ->assertJsonPath('data.merchant.rejection_reason', null);

        $this->withHeaders($headers)->postJson('/api/merchant-portal/orders', [])
            ->assertForbidden()
            ->assertJsonPath('code', 'MERCHANT_PENDING_APPROVAL');

        $this->withHeaders($headers)->getJson('/api/merchant-portal/warehouse/inventory')
            ->assertForbidden()
            ->assertJsonPath('code', 'MERCHANT_PENDING_APPROVAL');

        $this->withHeaders($headers)->postJson('/api/merchant-portal/finance/payouts/request', [])
            ->assertForbidden()
            ->assertJsonPath('code', 'MERCHANT_PENDING_APPROVAL');
    }

    public function test_user_without_permission_cannot_list_or_review_registrations(): void
    {
        $merchant = $this->registerMerchant();
        $staff = User::factory()->create([
            'role' => 'staff',
            'role_id' => Role::query()->where('slug', 'staff')->value('id'),
        ]);
        Sanctum::actingAs($staff);

        $this->getJson('/api/merchant-registration-requests')->assertForbidden();
        $this->getJson('/api/merchant-registration-requests/'.$merchant->id)->assertForbidden();
        $this->postJson('/api/merchant-registration-requests/'.$merchant->id.'/approve')->assertForbidden();
        $this->postJson('/api/merchant-registration-requests/'.$merchant->id.'/reject', ['reason' => 'No'])->assertForbidden();
    }

    public function test_merchant_portal_user_cannot_review_own_registration_even_if_globally_privileged(): void
    {
        $merchant = $this->registerMerchant();
        $owner = $merchant->primaryOwner()->with('user')->firstOrFail();
        $owner->user->update([
            'role' => 'admin',
            'role_id' => Role::query()->where('slug', 'super_admin')->value('id'),
        ]);
        Sanctum::actingAs($owner->user->fresh());

        $this->postJson('/api/merchant-registration-requests/'.$merchant->id.'/approve')
            ->assertForbidden()
            ->assertJsonPath('code', 'MERCHANT_SELF_REVIEW_FORBIDDEN');

        $this->assertSame('pending', $merchant->fresh()->status);
    }

    public function test_admin_can_list_filter_and_open_pending_registration_details(): void
    {
        $merchant = $this->registerMerchant();
        $this->actingAsSuperAdmin();

        $this->getJson('/api/merchant-registration-requests')
            ->assertOk()
            ->assertJsonPath('data.0.merchant_id', $merchant->id)
            ->assertJsonPath('data.0.owner_name', 'Ahmed Mohamed')
            ->assertJsonPath('data.0.owner_email', 'merchant@example.com')
            ->assertJsonPath('data.0.status', 'pending');

        $this->getJson('/api/merchant-registration-requests?search=Ahmed&status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/merchant-registration-requests/'.$merchant->id)
            ->assertOk()
            ->assertJsonPath('data.company.name', 'ABC Store')
            ->assertJsonPath('data.owner.email', 'merchant@example.com')
            ->assertJsonPath('data.registration.source', 'self_registration')
            ->assertJsonPath('data.registration.status', 'pending')
            ->assertJsonMissingPath('data.owner.password');
    }

    public function test_admin_can_approve_pending_registration_and_merchant_gets_operational_access(): void
    {
        $merchant = $this->registerMerchant();
        $owner = $merchant->primaryOwner()->with('user')->firstOrFail();
        $admin = $this->actingAsSuperAdmin();

        $this->postJson('/api/merchant-registration-requests/'.$merchant->id.'/approve', [
            'notes' => 'Approved after verification',
        ])->assertOk()
            ->assertJsonPath('data.merchant_id', $merchant->id)
            ->assertJsonPath('data.status', 'active');

        $merchant->refresh();
        $this->assertSame('active', $merchant->status);
        $this->assertSame($admin->id, $merchant->reviewed_by);
        $this->assertNotNull($merchant->reviewed_at);
        $this->assertSame('Approved after verification', $merchant->approval_notes);
        $this->assertSame('active', $owner->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $merchant->id,
            'event_type' => 'merchant_registration_approved',
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('merchant_notifications', [
            'merchant_id' => $merchant->id,
            'type' => 'merchant_registration_approved',
        ]);

        Sanctum::actingAs($owner->user);
        $this->getJson('/api/merchant-portal/dashboard/summary')->assertOk();
    }

    public function test_generic_merchant_update_cannot_bypass_pending_review_workflow(): void
    {
        $merchant = $this->registerMerchant();
        $this->actingAsSuperAdmin();

        $this->putJson('/api/merchants/'.$merchant->id, ['status' => 'active'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'USE_MERCHANT_REGISTRATION_REVIEW');

        $this->assertSame('pending', $merchant->fresh()->status);
    }

    public function test_approval_and_rejection_repeated_transitions_are_safely_rejected_without_duplicate_events(): void
    {
        $merchant = $this->registerMerchant();
        $this->actingAsSuperAdmin();

        $this->postJson('/api/merchant-registration-requests/'.$merchant->id.'/approve')->assertOk();
        $this->postJson('/api/merchant-registration-requests/'.$merchant->id.'/approve')
            ->assertStatus(409)
            ->assertJsonPath('code', 'INVALID_MERCHANT_REGISTRATION_TRANSITION');
        $this->postJson('/api/merchant-registration-requests/'.$merchant->id.'/reject', ['reason' => 'Late rejection'])
            ->assertStatus(409);

        $this->assertSame(1, AuditLog::query()
            ->where('auditable_id', $merchant->id)
            ->where('event_type', 'merchant_registration_approved')
            ->count());
        $this->assertSame(1, MerchantNotification::query()
            ->where('merchant_id', $merchant->id)
            ->where('type', 'merchant_registration_approved')
            ->count());
    }

    public function test_admin_can_reject_with_reason_and_rejected_merchant_can_only_read_status(): void
    {
        $merchant = $this->registerMerchant();
        $owner = $merchant->primaryOwner()->with('user')->firstOrFail();
        $admin = $this->actingAsSuperAdmin();

        $this->postJson('/api/merchant-registration-requests/'.$merchant->id.'/reject', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $reason = 'Business information could not be verified.';
        $this->postJson('/api/merchant-registration-requests/'.$merchant->id.'/reject', ['reason' => $reason])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.reason', $reason);

        $merchant->refresh();
        $this->assertSame('rejected', $merchant->status);
        $this->assertSame($reason, $merchant->rejection_reason);
        $this->assertSame($admin->id, $merchant->reviewed_by);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $merchant->id,
            'event_type' => 'merchant_registration_rejected',
        ]);

        Sanctum::actingAs($owner->user);
        $this->getJson('/api/merchant-portal/me')
            ->assertOk()
            ->assertJsonPath('data.merchant.status', 'rejected')
            ->assertJsonPath('data.merchant.rejection_reason', $reason);
        $this->postJson('/api/merchant-portal/orders', [])
            ->assertForbidden()
            ->assertJsonPath('code', 'MERCHANT_REGISTRATION_REJECTED');
    }

    public function test_suspended_merchant_receives_distinct_operational_access_error(): void
    {
        $merchant = $this->registerMerchant();
        $merchant->update(['status' => 'suspended']);
        $owner = $merchant->primaryOwner()->with('user')->firstOrFail();
        Sanctum::actingAs($owner->user);

        $this->getJson('/api/merchant-portal/me')
            ->assertOk()
            ->assertJsonPath('data.merchant.status', 'suspended');
        $this->getJson('/api/merchant-portal/dashboard/summary')
            ->assertForbidden()
            ->assertJsonPath('code', 'MERCHANT_SUSPENDED');
    }

    public function test_dashboard_reports_pending_registration_count(): void
    {
        $this->registerMerchant();
        $this->actingAsSuperAdmin();

        $this->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.pending_merchant_registrations', 1);
    }

    public function test_existing_admin_merchant_creation_remains_active_and_distinct(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/merchants', [
            'name' => 'Internal Merchant',
            'company_name' => 'Internal Merchant LLC',
            'email' => 'internal@example.com',
            'phone' => '01011111111',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.registration_source', 'admin');

        $this->assertDatabaseHas('merchants', [
            'email' => 'internal@example.com',
            'status' => 'active',
            'registration_source' => 'admin',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'ABC Store',
            'owner_name' => 'Ahmed Mohamed',
            'email' => 'merchant@example.com',
            'phone' => '01000000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'address' => 'Cairo, Egypt',
            'governorate_id' => null,
            'area_id' => null,
        ], $overrides);
    }

    private function registerMerchant(array $overrides = []): Merchant
    {
        $this->postJson('/api/merchant-portal/register', $this->registrationPayload($overrides))->assertCreated();

        return Merchant::query()->where('email', $overrides['email'] ?? 'merchant@example.com')->firstOrFail();
    }
}
