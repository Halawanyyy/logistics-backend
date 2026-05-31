<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\User;
use App\Modules\MerchantPortal\Enums\MerchantPortalRole;
use App\Modules\MerchantPortal\Enums\MerchantPortalUserStatus;
use App\Modules\MerchantPortal\Models\MerchantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class MerchantPortalPhaseOneTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_unauthenticated_request_to_merchant_portal_me_returns_401(): void
    {
        $this->getJson('/api/merchant-portal/me')
            ->assertStatus(401);
    }

    public function test_internal_user_without_membership_gets_403(): void
    {
        $user = $this->actingAsRole('staff');

        $this->getJson('/api/merchant-portal/me')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Merchant portal access is not available for this account.');

        $this->assertNotNull($user->id);
    }

    public function test_active_merchant_user_can_access_me_and_profile(): void
    {
        $merchant = $this->makeMerchant();
        $user = User::factory()->create([
            'role' => 'merchant_portal',
        ]);
        $merchantUser = $this->makeMerchantPortalUser($merchant, $user);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.merchant.id', $merchant->id)
            ->assertJsonPath('data.portal_role', $merchantUser->role)
            ->assertJsonPath('data.portal_status', $merchantUser->status)
            ->assertJsonPath('data.capabilities.0', 'profile.view');

        $this->getJson('/api/merchant-portal/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $merchant->id)
            ->assertJsonMissingPath('data.pricing_plan_id');
    }

    public function test_invited_suspended_deleted_and_inactive_memberships_get_403(): void
    {
        $merchant = $this->makeMerchant();
        $user = User::factory()->create(['role' => 'merchant_portal']);

        $invited = $this->makeMerchantPortalUser($merchant, $user, [
            'status' => MerchantPortalUserStatus::INVITED,
            'accepted_at' => null,
        ]);

        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/merchant-portal/me')->assertStatus(403);

        $invited->update(['status' => MerchantPortalUserStatus::SUSPENDED]);
        $this->getJson('/api/merchant-portal/me')->assertStatus(403);

        $invited->delete();
        $this->getJson('/api/merchant-portal/me')->assertStatus(403);

        $otherUser = User::factory()->create(['role' => 'merchant_portal']);
        $inactiveMerchant = $this->makeMerchant(['status' => 'inactive']);
        $this->makeMerchantPortalUser($inactiveMerchant, $otherUser);

        Sanctum::actingAs($otherUser->fresh());
        $this->getJson('/api/merchant-portal/me')->assertStatus(403);
    }

    public function test_merchant_id_query_override_is_rejected_for_portal_requests(): void
    {
        $merchantA = $this->makeMerchant();
        $merchantB = $this->makeMerchant(['email' => 'merchant-b@example.com']);
        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchantA, $user);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/me?merchant_id='.$merchantB->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('merchant_id');

        $this->getJson('/api/merchant-portal/profile?merchant_id='.$merchantB->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('merchant_id');
    }

    public function test_unauthorized_internal_user_cannot_manage_portal_users(): void
    {
        $merchant = $this->makeMerchant();
        $this->actingAsRole('staff');

        $this->postJson('/api/merchants/'.$merchant->id.'/portal-users', [
            'name' => 'Portal User',
            'email' => 'portal-user@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => MerchantPortalRole::STAFF,
            'status' => MerchantPortalUserStatus::ACTIVE,
        ])->assertStatus(403);
    }

    public function test_authorized_admin_can_create_portal_user_and_activate_user_access(): void
    {
        $merchant = $this->makeMerchant();
        $this->actingAsRole('admin');

        $response = $this->postJson('/api/merchants/'.$merchant->id.'/portal-users', [
            'name' => 'Portal Accountant',
            'email' => 'portal-accountant@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'phone' => '01000099999',
            'role' => MerchantPortalRole::ACCOUNTANT,
            'status' => MerchantPortalUserStatus::INVITED,
        ])->assertCreated()
            ->assertJsonPath('data.merchant_id', $merchant->id)
            ->assertJsonPath('data.role', MerchantPortalRole::ACCOUNTANT)
            ->assertJsonPath('data.status', MerchantPortalUserStatus::INVITED);

        $merchantUserId = $response->json('data.id');
        $userId = $response->json('data.user.id');

        $this->postJson('/api/merchants/'.$merchant->id.'/portal-users/'.$merchantUserId.'/activate')
            ->assertOk()
            ->assertJsonPath('data.status', MerchantPortalUserStatus::ACTIVE);

        $portalUser = User::query()->findOrFail($userId);
        Sanctum::actingAs($portalUser);

        $this->getJson('/api/merchant-portal/me')
            ->assertOk()
            ->assertJsonPath('data.merchant.id', $merchant->id)
            ->assertJsonPath('data.portal_role', MerchantPortalRole::ACCOUNTANT);
    }

    public function test_duplicate_same_merchant_membership_and_multiple_active_memberships_are_blocked(): void
    {
        $merchantA = $this->makeMerchant();
        $merchantB = $this->makeMerchant(['email' => 'merchant-b@example.com']);
        $this->actingAsRole('admin');

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchantA, $user);

        $this->postJson('/api/merchants/'.$merchantA->id.'/portal-users', [
            'user_id' => $user->id,
            'role' => MerchantPortalRole::STAFF,
            'status' => MerchantPortalUserStatus::ACTIVE,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('user_id');

        $this->postJson('/api/merchants/'.$merchantB->id.'/portal-users', [
            'user_id' => $user->id,
            'role' => MerchantPortalRole::STAFF,
            'status' => MerchantPortalUserStatus::ACTIVE,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }

    public function test_soft_deleted_membership_is_restored_for_same_merchant_without_duplicate(): void
    {
        $merchant = $this->makeMerchant();
        $this->actingAsRole('admin');

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $membership = $this->makeMerchantPortalUser($merchant, $user, [
            'role' => MerchantPortalRole::STAFF,
            'is_primary' => false,
        ]);
        $membership->delete();

        $this->postJson('/api/merchants/'.$merchant->id.'/portal-users', [
            'user_id' => $user->id,
            'role' => MerchantPortalRole::STAFF,
            'status' => MerchantPortalUserStatus::ACTIVE,
            'is_primary' => false,
        ])->assertCreated();

        $this->assertSame(1, MerchantUser::withTrashed()->where('merchant_id', $merchant->id)->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('merchant_users', [
            'id' => $membership->id,
            'deleted_at' => null,
        ]);
    }

    public function test_merchant_cannot_have_two_active_primary_owners(): void
    {
        $merchant = $this->makeMerchant();
        $this->actingAsRole('admin');

        $firstOwner = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $firstOwner);

        $secondOwner = User::factory()->create(['role' => 'merchant_portal']);

        $this->postJson('/api/merchants/'.$merchant->id.'/portal-users', [
            'user_id' => $secondOwner->id,
            'role' => MerchantPortalRole::OWNER,
            'status' => MerchantPortalUserStatus::ACTIVE,
            'is_primary' => true,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('is_primary');
    }

    public function test_cannot_suspend_or_delete_the_only_active_owner_but_can_when_another_owner_exists(): void
    {
        $merchant = $this->makeMerchant();
        $this->actingAsRole('admin');

        $ownerUser = User::factory()->create(['role' => 'merchant_portal']);
        $owner = $this->makeMerchantPortalUser($merchant, $ownerUser);

        $this->postJson('/api/merchants/'.$merchant->id.'/portal-users/'.$owner->id.'/suspend')
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $this->deleteJson('/api/merchants/'.$merchant->id.'/portal-users/'.$owner->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $this->putJson('/api/merchants/'.$merchant->id.'/portal-users/'.$owner->id, [
            'role' => MerchantPortalRole::STAFF,
            'is_primary' => false,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $otherOwnerUser = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $otherOwnerUser, [
            'is_primary' => false,
            'role' => MerchantPortalRole::OWNER,
        ]);

        $this->postJson('/api/merchants/'.$merchant->id.'/portal-users/'.$owner->id.'/suspend')
            ->assertOk()
            ->assertJsonPath('data.status', MerchantPortalUserStatus::SUSPENDED);
    }

    public function test_invalid_role_status_inactive_merchant_and_route_mismatch_are_blocked(): void
    {
        $inactiveMerchant = $this->makeMerchant(['status' => 'inactive']);
        $activeMerchant = $this->makeMerchant(['email' => 'active@example.com']);
        $otherMerchant = $this->makeMerchant(['email' => 'other@example.com']);
        $this->actingAsRole('admin');

        $this->postJson('/api/merchants/'.$activeMerchant->id.'/portal-users', [
            'name' => 'Invalid Role',
            'email' => 'invalid-role@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'invalid',
            'status' => MerchantPortalUserStatus::ACTIVE,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $this->postJson('/api/merchants/'.$activeMerchant->id.'/portal-users', [
            'name' => 'Invalid Status',
            'email' => 'invalid-status@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => MerchantPortalRole::STAFF,
            'status' => 'invalid',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->postJson('/api/merchants/'.$inactiveMerchant->id.'/portal-users', [
            'name' => 'Blocked Merchant',
            'email' => 'blocked-merchant@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => MerchantPortalRole::STAFF,
            'status' => MerchantPortalUserStatus::ACTIVE,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('merchant');

        $membership = $this->makeMerchantPortalUser($otherMerchant, User::factory()->create(['role' => 'merchant_portal']));

        $this->getJson('/api/merchants/'.$activeMerchant->id.'/portal-users/'.$membership->id)
            ->assertStatus(404);
    }

    public function test_role_capabilities_are_scoped_correctly(): void
    {
        $merchant = $this->makeMerchant();

        $accountantUser = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $accountantUser, [
            'role' => MerchantPortalRole::ACCOUNTANT,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($accountantUser->fresh());
        $accountantResponse = $this->getJson('/api/merchant-portal/me')
            ->assertOk();
        $this->assertContains('finance.view', $accountantResponse->json('data.capabilities'));
        $this->assertNotContains('integrations.manage', $accountantResponse->json('data.capabilities'));

        $staffUser = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $staffUser, [
            'role' => MerchantPortalRole::STAFF,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($staffUser->fresh());
        $staffResponse = $this->getJson('/api/merchant-portal/me')
            ->assertOk();
        $this->assertContains('orders.update', $staffResponse->json('data.capabilities'));
        $this->assertNotContains('finance.view', $staffResponse->json('data.capabilities'));

        $warehouseViewerUser = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $warehouseViewerUser, [
            'role' => MerchantPortalRole::WAREHOUSE_VIEWER,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($warehouseViewerUser->fresh());
        $warehouseViewerResponse = $this->getJson('/api/merchant-portal/me')
            ->assertOk();
        $this->assertContains('warehouse.view', $warehouseViewerResponse->json('data.capabilities'));
        $this->assertContains('labels.print', $warehouseViewerResponse->json('data.capabilities'));
        $this->assertNotContains('finance.view', $warehouseViewerResponse->json('data.capabilities'));
    }

    public function test_portal_user_creation_rejects_duplicate_email_and_prohibited_merchant_id_payload(): void
    {
        $merchant = $this->makeMerchant();
        $this->actingAsRole('admin');

        User::factory()->create([
            'email' => 'existing@example.com',
            'role' => 'merchant_portal',
        ]);

        $this->postJson('/api/merchants/'.$merchant->id.'/portal-users', [
            'merchant_id' => $merchant->id,
            'name' => 'Portal Duplicate',
            'email' => 'existing@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => MerchantPortalRole::STAFF,
            'status' => MerchantPortalUserStatus::ACTIVE,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['merchant_id', 'email']);
    }
}
