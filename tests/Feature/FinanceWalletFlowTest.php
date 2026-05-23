<?php

namespace Tests\Feature;

use App\Modules\Finance\Enums\MerchantWalletTransactionType;
use App\Modules\Finance\Models\MerchantWalletTransaction;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Shipment\Enums\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class FinanceWalletFlowTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_delivered_cod_creates_wallet_credit_and_shipping_fee_debit(): void
    {
        $this->actingAsSuperAdmin();

        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $order = $this->makeOrder($merchant, $governorate, $area, [
            'status' => OrderStatus::CONFIRMED,
            'cod_amount' => '250.00',
            'shipping_fee' => '40.00',
        ], [[
            'product_name' => 'Watch',
            'quantity' => 1,
            'unit_price' => '250.00',
        ]]);

        $shipmentId = $this->postJson('/api/orders/'.$order->id.'/shipments')
            ->assertOk()
            ->json('data.id');

        $this->postJson('/api/shipments/'.$shipmentId.'/complete-delivery', [
            'shipment_status' => ShipmentStatus::DELIVERED,
            'items' => [
                [
                    'order_item_id' => $order->items->first()->id,
                    'result_status' => 'delivered',
                    'quantity' => 1,
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('merchant_wallet_transactions', [
            'merchant_id' => $merchant->id,
            'transaction_type' => MerchantWalletTransactionType::COD_COLLECTED,
            'direction' => 'credit',
            'status' => 'approved',
            'amount' => '250.00',
        ]);

        $this->assertDatabaseHas('merchant_wallet_transactions', [
            'merchant_id' => $merchant->id,
            'transaction_type' => MerchantWalletTransactionType::SHIPPING_FEE,
            'direction' => 'debit',
            'status' => 'approved',
            'amount' => '40.00',
        ]);
    }

    public function test_manual_deposit_approval_and_payout_completion_create_expected_ledgers(): void
    {
        $merchant = $this->makeMerchant();
        $financeUser = $this->actingAsRole('finance');

        $depositResponse = $this->postJson('/api/merchants/'.$merchant->id.'/wallet/deposit', [
            'amount' => 500,
            'reason' => 'Manual adjustment',
            'notes' => 'Pending deposit',
        ]);

        $transactionId = $depositResponse->json('data.id');

        $depositResponse
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->actingAsSuperAdmin();

        $this->postJson('/api/merchant-wallet-transactions/'.$transactionId.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $approvedTransaction = MerchantWalletTransaction::query()->findOrFail($transactionId);
        $this->assertNotNull($approvedTransaction->balance_after);

        $treasuryAccount = $this->makeTreasuryAccount();

        $payoutId = $this->postJson('/api/merchant-payouts', [
            'merchant_id' => $merchant->id,
            'amount' => 100,
            'payment_method' => 'cash',
            'notes' => 'Wallet payout',
        ])->assertCreated()
            ->json('data.id');

        $this->postJson('/api/merchant-payouts/'.$payoutId.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->postJson('/api/merchant-payouts/'.$payoutId.'/complete', [
            'treasury_account_id' => $treasuryAccount->id,
            'payment_method' => 'cash',
            'reference_number' => 'TRX-100',
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('merchant_wallet_transactions', [
            'merchant_id' => $merchant->id,
            'transaction_type' => MerchantWalletTransactionType::PAYOUT,
            'direction' => 'debit',
            'status' => 'approved',
            'amount' => '100.00',
        ]);

        $this->assertDatabaseHas('treasury_transactions', [
            'treasury_account_id' => $treasuryAccount->id,
            'source_type' => 'merchant_payout',
            'direction' => 'debit',
            'amount' => '100.00',
        ]);

        $this->assertNotNull($financeUser->id);
    }
}
