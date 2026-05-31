<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Finance\Models\MerchantPayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
        Storage::fake('attachments');
        Storage::fake('exports');
        Storage::fake('local');
    }

    public function test_deleted_user_cannot_authenticate_and_auth_me_hides_sensitive_fields(): void
    {
        $deletedUser = User::factory()->create([
            'email' => 'deleted@example.com',
            'password' => Hash::make('secret123'),
        ]);
        $deletedUser->delete();

        $this->postJson('/api/auth/login', [
            'email' => 'deleted@example.com',
            'password' => 'secret123',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid credentials');

        $activeUser = User::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->actingAs($activeUser)
            ->getJson('/api/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $activeUser->id)
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token')
            ->assertJsonMissingPath('user.tokens');
    }

    public function test_sensitive_routes_keep_expected_throttle_middleware(): void
    {
        $this->assertContains(
            'throttle:auth-login',
            $this->findRoute('api/auth/login', 'POST')->gatherMiddleware()
        );

        $this->assertContains(
            'throttle:public-tracking',
            $this->findRoute('api/track/{shipment_number}', 'GET')->gatherMiddleware()
        );

        $integrationMeRoute = $this->findRoute('api/integrations/me', 'GET')->gatherMiddleware();
        $integrationOrdersRoute = $this->findRoute('api/integrations/orders', 'POST')->gatherMiddleware();

        $this->assertContains('merchant.integration.token', $integrationMeRoute);
        $this->assertContains('throttle:integration-api', $integrationMeRoute);
        $this->assertContains('merchant.integration.token', $integrationOrdersRoute);
        $this->assertContains('throttle:integration-api', $integrationOrdersRoute);
    }

    public function test_integration_plain_token_is_only_returned_once(): void
    {
        $merchant = $this->makeMerchant();
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/merchants/'.$merchant->id.'/integration-tokens', [
            'name' => 'Security Token',
            'provider' => 'woocommerce',
            'permissions' => ['orders.create', 'orders.view'],
        ])->assertCreated()
            ->assertJsonMissingPath('data.token_hash');

        $tokenId = $response->json('data.id');
        $this->assertIsString($response->json('data.plain_token'));

        $this->getJson('/api/merchant-integration-tokens/'.$tokenId)
            ->assertOk()
            ->assertJsonMissingPath('data.plain_token')
            ->assertJsonMissingPath('data.token_hash');
    }

    public function test_internal_attachment_export_and_invoice_resources_hide_file_paths(): void
    {
        $merchant = $this->makeMerchant();
        $this->actingAsSuperAdmin();

        $attachmentId = $this->postJson('/api/attachments', [
            'attachable_type' => 'merchants',
            'attachable_id' => $merchant->id,
            'category' => 'document',
            'file' => UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'),
        ])->assertCreated()
            ->assertJsonMissingPath('data.file_path')
            ->json('data.id');

        $this->getJson('/api/attachments/'.$attachmentId)
            ->assertOk()
            ->assertJsonMissingPath('data.file_path');

        $exportId = $this->postJson('/api/exports/audit_logs', [
            'format' => 'csv',
        ])->assertCreated()
            ->assertJsonMissingPath('data.file_path')
            ->json('data.id');

        $this->getJson('/api/exports/'.$exportId)
            ->assertOk()
            ->assertJsonMissingPath('data.file_path');

        [$governorate, $area] = $this->makeLocation();
        $invoiceMerchant = $this->makeMerchant(['email' => 'invoice-security@example.com']);
        $this->makeOrder($invoiceMerchant, $governorate, $area);
        $invoice = $this->makeMerchantInvoice($invoiceMerchant, [
            'file_path' => 'private/invoices/security.pdf',
        ]);

        $this->getJson('/api/merchant-invoices/'.$invoice->id)
            ->assertOk()
            ->assertJsonMissingPath('data.file_path');
    }

    public function test_malicious_stored_download_paths_are_rejected(): void
    {
        $merchant = $this->makeMerchant();
        $this->actingAsSuperAdmin();

        $attachment = $this->makeAttachment($merchant, [
            'file_path' => 'attachments/../../secrets.txt',
        ]);

        $this->get('/api/attachments/'.$attachment->id.'/download')
            ->assertStatus(404);

        $export = $this->makeExportLog([
            'file_path' => 'exports/../../ledger.csv',
        ]);

        $this->get('/api/exports/'.$export->id.'/download')
            ->assertStatus(404);

        $invoice = $this->makeMerchantInvoice($merchant, [
            'file_path' => '../private/invoices/bad.pdf',
        ]);

        $this->get('/api/merchant-invoices/'.$invoice->id.'/download')
            ->assertStatus(404);
    }

    public function test_portal_integration_token_actions_and_payout_requests_are_audited(): void
    {
        $merchant = $this->makeMerchant();
        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user);
        $this->makeMerchantWalletTransaction($merchant, [
            'amount' => '500.00',
            'balance_after' => '500.00',
        ]);

        Sanctum::actingAs($user->fresh());

        $tokenId = $this->postJson('/api/merchant-portal/integration-tokens', [
            'name' => 'Portal Security Token',
            'abilities' => ['orders.create', 'orders.view'],
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/merchant-portal/integration-tokens/'.$tokenId.'/revoke')
            ->assertOk();

        $this->deleteJson('/api/merchant-portal/integration-tokens/'.$tokenId)
            ->assertOk();

        $payoutId = $this->postJson('/api/merchant-portal/finance/payouts/request', [
            'amount' => 100,
            'method' => 'bank_transfer',
            'reference_number' => 'PORTAL-PAYOUT-SECURITY',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEventType::MERCHANT_INTEGRATION_TOKEN_CREATED,
            'auditable_type' => 'merchant_integration_token',
            'auditable_id' => $tokenId,
            'action' => 'created',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEventType::MERCHANT_INTEGRATION_TOKEN_REVOKED,
            'auditable_type' => 'merchant_integration_token',
            'auditable_id' => $tokenId,
            'action' => 'revoked',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEventType::MERCHANT_INTEGRATION_TOKEN_DELETED,
            'auditable_type' => 'merchant_integration_token',
            'auditable_id' => $tokenId,
            'action' => 'deleted',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEventType::PAYOUT_CREATED,
            'auditable_type' => 'merchant_payout',
            'auditable_id' => $payoutId,
            'action' => 'created',
        ]);

        $this->assertNotNull(AuditLog::query()->where('auditable_id', $payoutId)->first());
        $this->assertNotNull(MerchantPayout::query()->find($payoutId));
    }

    private function findRoute(string $uri, string $method): IlluminateRoute
    {
        $route = collect(app('router')->getRoutes()->getRoutes())->first(
            fn (IlluminateRoute $route): bool => $route->uri() === $uri && in_array($method, $route->methods(), true)
        );

        $this->assertInstanceOf(IlluminateRoute::class, $route, sprintf('Route [%s %s] was not found.', $method, $uri));

        return $route;
    }
}
