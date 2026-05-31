<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\MerchantPortal\Enums\MerchantPortalRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class MerchantPortalPhaseThreeFinanceTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_merchant_portal_finance_endpoints_require_auth_and_active_membership(): void
    {
        $this->getJson('/api/merchant-portal/finance/summary')
            ->assertStatus(401);

        $this->actingAsRole('staff');

        $this->getJson('/api/merchant-portal/finance/summary')
            ->assertStatus(403);
    }

    public function test_merchant_user_can_only_access_own_finance_entities_and_merchant_id_is_rejected(): void
    {
        $merchantA = $this->makeMerchant();
        $merchantB = $this->makeMerchant(['email' => 'merchant-finance-b@example.com']);

        $walletTransactionA = $this->makeMerchantWalletTransaction($merchantA, [
            'transaction_number' => 'MWT-A-000001',
        ]);
        $walletTransactionB = $this->makeMerchantWalletTransaction($merchantB, [
            'transaction_number' => 'MWT-B-000001',
        ]);
        $invoiceA = $this->makeMerchantInvoice($merchantA, [
            'invoice_number' => 'INV-A-000001',
        ]);
        $invoiceB = $this->makeMerchantInvoice($merchantB, [
            'invoice_number' => 'INV-B-000001',
        ]);
        $payoutA = $this->makeMerchantPayout($merchantA, [
            'reference_number' => 'PO-A-000001',
        ]);
        $payoutB = $this->makeMerchantPayout($merchantB, [
            'reference_number' => 'PO-B-000001',
        ]);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchantA, $user, [
            'role' => MerchantPortalRole::ACCOUNTANT,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/finance/summary?merchant_id='.$merchantB->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('merchant_id');

        $this->getJson('/api/merchant-portal/finance/invoices/'.$invoiceA->id.'/preview?merchant_id='.$merchantB->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('merchant_id');

        $this->postJson('/api/merchant-portal/finance/payouts/request', [
            'merchant_id' => $merchantB->id,
            'amount' => 50,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('merchant_id');

        $this->getJson('/api/merchant-portal/finance/wallet/transactions/'.$walletTransactionA->id)
            ->assertOk()
            ->assertJsonPath('data.id', $walletTransactionA->id);

        $this->getJson('/api/merchant-portal/finance/wallet/transactions/'.$walletTransactionB->id)
            ->assertStatus(404);

        $this->getJson('/api/merchant-portal/finance/invoices/'.$invoiceA->id)
            ->assertOk()
            ->assertJsonPath('data.id', $invoiceA->id);

        $this->getJson('/api/merchant-portal/finance/invoices/'.$invoiceB->id)
            ->assertStatus(404);

        $this->get('/api/merchant-portal/finance/invoices/'.$invoiceB->id.'/download')
            ->assertStatus(404);

        $this->getJson('/api/merchant-portal/finance/payouts/'.$payoutA->id)
            ->assertOk()
            ->assertJsonPath('data.id', $payoutA->id);

        $this->getJson('/api/merchant-portal/finance/payouts/'.$payoutB->id)
            ->assertStatus(404);
    }

    public function test_merchant_can_view_own_finance_and_wallet_summaries_without_internal_fields(): void
    {
        $merchant = $this->makeMerchant();
        $otherMerchant = $this->makeMerchant(['email' => 'summary-other@example.com']);

        $this->makeMerchantWalletTransaction($merchant, [
            'transaction_type' => 'cod_collected',
            'direction' => 'credit',
            'amount' => '500.00',
            'balance_after' => '500.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->makeMerchantWalletTransaction($merchant, [
            'transaction_type' => 'shipping_fee',
            'direction' => 'debit',
            'amount' => '40.00',
            'balance_after' => '460.00',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $invoice = $this->makeMerchantInvoice($merchant, [
            'status' => 'issued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->makeMerchantPayout($merchant, [
            'status' => 'pending',
            'amount' => '100.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->makeMerchantWalletTransaction($otherMerchant, [
            'transaction_type' => 'cod_collected',
            'direction' => 'credit',
            'amount' => '999.00',
            'balance_after' => '999.00',
        ]);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, [
            'role' => MerchantPortalRole::ACCOUNTANT,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/finance/summary?date_from='.now()->subDay()->toDateString().'&date_to='.now()->addDay()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.merchant_id', $merchant->id)
            ->assertJsonPath('data.total_invoices', 1)
            ->assertJsonPath('data.pending_payouts_count', 1)
            ->assertJsonPath('data.latest_invoice.id', $invoice->id)
            ->assertJsonMissingPath('data.company_profit')
            ->assertJsonMissingPath('data.reconciliation_summary');

        $walletSummaryResponse = $this->getJson('/api/merchant-portal/finance/wallet/summary')
            ->assertOk()
            ->assertJsonPath('data.merchant_id', $merchant->id)
            ->assertJsonPath('data.total_credits', '500.00');

        $this->assertNotNull($walletSummaryResponse->json('data.last_transaction_at'));
    }

    public function test_merchant_can_list_show_and_filter_wallet_transactions_but_cannot_manage_them(): void
    {
        $merchant = $this->makeMerchant();
        $otherMerchant = $this->makeMerchant(['email' => 'wallet-other@example.com']);

        $ownTransaction = $this->makeMerchantWalletTransaction($merchant, [
            'transaction_number' => 'MWT-MERCHANT-SEARCH',
            'transaction_type' => 'warehouse_charge',
            'direction' => 'debit',
            'amount' => '75.00',
            'balance_after' => '425.00',
            'reference_number' => 'REF-SEARCH-001',
        ]);
        $this->makeMerchantWalletTransaction($otherMerchant, [
            'transaction_number' => 'MWT-OTHER-SEARCH',
            'transaction_type' => 'warehouse_charge',
            'direction' => 'debit',
            'amount' => '75.00',
            'balance_after' => '425.00',
        ]);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, [
            'role' => MerchantPortalRole::OWNER,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/finance/wallet/transactions?type=warehouse_charge&direction=debit&search=SEARCH')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownTransaction->id)
            ->assertJsonMissingPath('data.0.notes');

        $this->getJson('/api/merchant-portal/finance/wallet/transactions/'.$ownTransaction->id)
            ->assertOk()
            ->assertJsonPath('data.id', $ownTransaction->id)
            ->assertJsonMissingPath('data.approved_by');

        $this->postJson('/api/merchant-portal/finance/wallet/transactions', [
            'amount' => 100,
        ])->assertStatus(405);

        $this->postJson('/api/merchant-portal/finance/wallet/transactions/'.$ownTransaction->id.'/approve')
            ->assertStatus(404);

        $this->postJson('/api/merchant-portal/finance/wallet/transactions/'.$ownTransaction->id.'/reject')
            ->assertStatus(404);
    }

    public function test_merchant_can_list_show_preview_and_download_own_invoices_only(): void
    {
        $merchant = $this->makeMerchant();
        $otherMerchant = $this->makeMerchant(['email' => 'invoice-other@example.com']);

        $ownInvoice = $this->makeMerchantInvoice($merchant, [
            'invoice_number' => 'INV-PORTAL-000001',
            'status' => 'issued',
        ], [[
            'item_type' => 'shipping_fee',
            'description' => 'Shipping',
            'quantity' => 1,
            'unit_amount' => '25.00',
            'total_amount' => '25.00',
        ]]);
        $otherInvoice = $this->makeMerchantInvoice($otherMerchant, [
            'invoice_number' => 'INV-PORTAL-000002',
            'status' => 'issued',
        ]);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, [
            'role' => MerchantPortalRole::ACCOUNTANT,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/finance/invoices?status=issued&search=PORTAL')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownInvoice->id);

        $this->getJson('/api/merchant-portal/finance/invoices/'.$ownInvoice->id)
            ->assertOk()
            ->assertJsonPath('data.id', $ownInvoice->id)
            ->assertJsonPath('data.items.0.description', 'Shipping')
            ->assertJsonMissingPath('data.file_path');

        $this->getJson('/api/merchant-portal/finance/invoices/'.$ownInvoice->id.'/preview')
            ->assertOk()
            ->assertJsonPath('data.invoice_number', $ownInvoice->invoice_number);

        $this->get('/api/merchant-portal/finance/invoices/'.$ownInvoice->id.'/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->getJson('/api/merchant-portal/finance/invoices/'.$otherInvoice->id)
            ->assertStatus(404);

        $this->postJson('/api/merchant-portal/finance/invoices', [
            'merchant_id' => $merchant->id,
        ])->assertStatus(405);

        $this->putJson('/api/merchant-portal/finance/invoices/'.$ownInvoice->id, [
            'status' => 'paid',
        ])->assertStatus(405);

        $this->deleteJson('/api/merchant-portal/finance/invoices/'.$ownInvoice->id)
            ->assertStatus(405);
    }

    public function test_merchant_can_list_show_and_request_own_payouts_but_cannot_manage_them_directly(): void
    {
        $merchant = $this->makeMerchant();
        $otherMerchant = $this->makeMerchant(['email' => 'payout-other@example.com']);

        $this->makeMerchantWalletTransaction($merchant, [
            'transaction_type' => 'cod_collected',
            'direction' => 'credit',
            'amount' => '1500.00',
            'balance_after' => '1500.00',
        ]);

        $ownPayout = $this->makeMerchantPayout($merchant, [
            'reference_number' => 'PAYOUT-PORTAL-000001',
            'status' => 'pending',
        ]);
        $otherPayout = $this->makeMerchantPayout($otherMerchant, [
            'reference_number' => 'PAYOUT-PORTAL-000002',
            'status' => 'pending',
        ]);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, [
            'role' => MerchantPortalRole::ACCOUNTANT,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/finance/payouts?status=pending&search=PORTAL')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownPayout->id);

        $this->getJson('/api/merchant-portal/finance/payouts/'.$ownPayout->id)
            ->assertOk()
            ->assertJsonPath('data.id', $ownPayout->id);

        $response = $this->postJson('/api/merchant-portal/finance/payouts/request', [
            'amount' => 250,
            'method' => 'bank_transfer',
            'bank_account_name' => 'ABC Store',
            'bank_account_number' => '123456789',
            'bank_name' => 'Test Bank',
            'notes' => 'Please process payout',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', '250.00');

        $payoutId = $response->json('data.id');

        $this->assertDatabaseHas('merchant_payouts', [
            'id' => $payoutId,
            'merchant_id' => $merchant->id,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        $this->postJson('/api/merchant-portal/finance/payouts/request', [
            'amount' => 5000,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Payout amount exceeds merchant available balance.');

        $this->postJson('/api/merchant-portal/finance/payouts/request', [
            'amount' => 50,
            'status' => 'completed',
            'approved_by' => 1,
            'treasury_account_id' => 1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'approved_by', 'treasury_account_id']);

        $this->getJson('/api/merchant-portal/finance/payouts/'.$otherPayout->id)
            ->assertStatus(404);

        $this->postJson('/api/merchant-portal/finance/payouts/'.$ownPayout->id.'/approve')
            ->assertStatus(404);

        $this->postJson('/api/merchant-portal/finance/payouts/'.$ownPayout->id.'/reject', [
            'rejection_reason' => 'Blocked',
        ])->assertStatus(404);

        $this->postJson('/api/merchant-portal/finance/payouts/'.$ownPayout->id.'/complete', [
            'payment_method' => 'cash',
        ])->assertStatus(404);
    }

    public function test_finance_capabilities_are_enforced_for_portal_roles(): void
    {
        $merchant = $this->makeMerchant();
        $invoice = $this->makeMerchantInvoice($merchant);
        $this->makeMerchantWalletTransaction($merchant, [
            'transaction_type' => 'cod_collected',
            'direction' => 'credit',
            'amount' => '300.00',
            'balance_after' => '300.00',
        ]);

        $owner = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $owner, [
            'role' => MerchantPortalRole::OWNER,
        ]);

        Sanctum::actingAs($owner->fresh());
        $this->getJson('/api/merchant-portal/finance/summary')->assertOk();
        $this->get('/api/merchant-portal/finance/invoices/'.$invoice->id.'/download')->assertOk();

        $admin = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $admin, [
            'role' => MerchantPortalRole::ADMIN,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($admin->fresh());
        $this->getJson('/api/merchant-portal/finance/wallet/transactions')->assertOk();
        $this->postJson('/api/merchant-portal/finance/payouts/request', [
            'amount' => 50,
        ])->assertCreated();

        $staff = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $staff, [
            'role' => MerchantPortalRole::STAFF,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($staff->fresh());
        $this->getJson('/api/merchant-portal/finance/summary')->assertStatus(403);
        $this->get('/api/merchant-portal/finance/invoices/'.$invoice->id.'/download')->assertStatus(403);
        $this->postJson('/api/merchant-portal/finance/payouts/request', [
            'amount' => 50,
        ])->assertStatus(403);

        $warehouseViewer = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $warehouseViewer, [
            'role' => MerchantPortalRole::WAREHOUSE_VIEWER,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($warehouseViewer->fresh());
        $this->getJson('/api/merchant-portal/finance/summary')->assertStatus(403);
    }

    public function test_existing_internal_finance_apis_still_work(): void
    {
        $merchant = $this->makeMerchant();
        $this->actingAsSuperAdmin();

        $this->getJson('/api/merchants/'.$merchant->id.'/finance/summary')
            ->assertOk()
            ->assertJsonPath('data.merchant_id', $merchant->id);

        $this->postJson('/api/merchants/'.$merchant->id.'/wallet/deposit', [
            'amount' => 500,
            'reason' => 'Internal finance deposit',
        ])->assertCreated();
    }
}
