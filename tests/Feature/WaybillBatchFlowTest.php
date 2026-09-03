<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Order\Actions\CreateOrderAction;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\RolePermission\Models\Role;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Settings\Services\SystemSettingService;
use App\Modules\Waybill\Enums\WaybillSource;
use App\Modules\Waybill\Enums\WaybillStatus;
use App\Modules\Waybill\Models\NumberSequence;
use App\Modules\Waybill\Models\Waybill;
use App\Modules\Waybill\Models\WaybillBatch;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class WaybillBatchFlowTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_admin_generates_exact_unique_preprinted_batch_with_range_and_audit(): void
    {
        $admin = $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/waybill-batches', [
            'quantity' => 50,
            'label_template' => 'default',
            'notes' => 'Warehouse desk batch',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.quantity', 50)
            ->assertJsonPath('data.first_waybill_number', 'WB-000001')
            ->assertJsonPath('data.last_waybill_number', 'WB-000050')
            ->assertJsonPath('data.available_count', 50)
            ->assertJsonPath('data.assigned_count', 0)
            ->assertJsonPath('data.void_count', 0);

        $batch = WaybillBatch::query()->firstOrFail();
        $numbers = Waybill::query()->where('batch_id', $batch->id)->pluck('waybill_number');

        $this->assertCount(50, $numbers);
        $this->assertCount(50, $numbers->unique());
        $this->assertSame(50, Waybill::query()->where('status', WaybillStatus::AVAILABLE)->count());
        $this->assertSame(50, Waybill::query()->where('source', WaybillSource::PREPRINTED)->count());
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'waybill_batch_generated',
            'auditable_id' => $batch->id,
            'user_id' => $admin->id,
        ]);
        $this->assertSame(50, NumberSequence::query()->where('key', 'waybill')->value('current_value'));

        $this->getJson('/api/waybill-batches')
            ->assertOk()
            ->assertJsonPath('summary.total_generated', 50)
            ->assertJsonPath('summary.available', 50)
            ->assertJsonPath('summary.assigned', 0)
            ->assertJsonPath('summary.void', 0);
    }

    public function test_two_batches_do_not_overlap_and_automatic_waybill_follows_reserved_ranges(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/waybill-batches', ['quantity' => 10])->assertCreated();
        $this->postJson('/api/waybill-batches', ['quantity' => 5])->assertCreated();

        $batches = WaybillBatch::query()->orderBy('id')->get();
        $this->assertSame('WB-000001', $batches[0]->first_waybill_number);
        $this->assertSame('WB-000010', $batches[0]->last_waybill_number);
        $this->assertSame('WB-000011', $batches[1]->first_waybill_number);
        $this->assertSame('WB-000015', $batches[1]->last_waybill_number);

        [$merchant, $order] = $this->confirmedOrder();
        $shipment = $this->postJson('/api/orders/'.$order->id.'/shipments')
            ->assertOk()
            ->json('data');

        $automatic = Waybill::query()->where('shipment_id', $shipment['id'])->firstOrFail();
        $this->assertSame('WB-000016', $automatic->waybill_number);
        $this->assertSame(WaybillSource::AUTOMATIC, $automatic->source);
        $this->assertSame(WaybillStatus::ASSIGNED, $automatic->status);
        $this->assertSame($merchant->id, $automatic->merchant_id);
        $this->assertDatabaseMissing('waybills', [
            'source' => WaybillSource::AUTOMATIC,
            'waybill_number' => 'WB-000001',
        ]);
    }

    public function test_database_prevents_duplicate_waybill_numbers_and_barcodes(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/waybill-batches', ['quantity' => 1])->assertCreated();
        $existing = Waybill::query()->firstOrFail();

        $this->expectException(QueryException::class);
        Waybill::query()->create([
            'waybill_number' => $existing->waybill_number,
            'barcode' => 'ANOTHER-BARCODE',
            'status' => WaybillStatus::AVAILABLE,
            'source' => WaybillSource::PREPRINTED,
            'generated_at' => now(),
        ]);
    }

    public function test_database_prevents_duplicate_waybill_barcodes(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/waybill-batches', ['quantity' => 1])->assertCreated();
        $existing = Waybill::query()->firstOrFail();

        $this->expectException(QueryException::class);
        Waybill::query()->create([
            'waybill_number' => 'WB-999999',
            'barcode' => $existing->barcode,
            'status' => WaybillStatus::AVAILABLE,
            'source' => WaybillSource::PREPRINTED,
            'generated_at' => now(),
        ]);
    }

    public function test_reprinting_returns_identical_numbers_without_advancing_sequence(): void
    {
        $this->actingAsSuperAdmin();
        $batchId = $this->postJson('/api/waybill-batches', ['quantity' => 3])
            ->assertCreated()
            ->json('data.id');
        $before = Waybill::query()->orderBy('id')->pluck('waybill_number')->all();

        $first = $this->getJson('/api/waybill-batches/'.$batchId.'/print?format=json')->assertOk();
        $second = $this->getJson('/api/waybill-batches/'.$batchId.'/print?format=json')->assertOk();

        $this->assertSame($before, Waybill::query()->orderBy('id')->pluck('waybill_number')->all());
        $this->assertSame(3, Waybill::query()->count());
        $this->assertSame(3, NumberSequence::query()->where('key', 'waybill')->value('current_value'));
        $this->assertSame($first->json('data'), $second->json('data'));
        $this->assertSame(2, DB::table('audit_logs')->where('event_type', 'waybill_batch_reprinted')->count());
    }

    public function test_simple_label_is_exact_minimal_payload_and_reprint_is_read_only(): void
    {
        $this->actingAsSuperAdmin();
        $waybillNumber = $this->generateOneWaybill();
        $waybill = Waybill::query()->where('waybill_number', $waybillNumber)->firstOrFail();
        $waybill->update(['barcode' => 'LEGACY-STORED-BARCODE']);
        $sequenceBefore = NumberSequence::query()->where('key', 'waybill')->value('current_value');

        $expected = [
            'data' => [
                'waybill_number' => $waybillNumber,
                'barcode' => $waybillNumber,
            ],
        ];

        $this->getJson('/api/waybills/'.$waybill->id.'/simple-label?format=json')
            ->assertOk()
            ->assertExactJson($expected);
        $this->postJson('/api/barcodes/lookup', ['barcode' => $waybillNumber])
            ->assertOk()
            ->assertJsonPath('data.type', 'waybill')
            ->assertJsonPath('data.data.waybill_number', $waybillNumber);
        $this->getJson('/api/waybills/'.$waybill->id.'/simple-label?format=json')
            ->assertOk()
            ->assertExactJson($expected);

        $waybill->refresh();
        $this->assertSame(WaybillStatus::AVAILABLE, $waybill->status);
        $this->assertNull($waybill->order_id);
        $this->assertNull($waybill->shipment_id);
        $this->assertSame(1, Waybill::query()->count());
        $this->assertSame($sequenceBefore, NumberSequence::query()->where('key', 'waybill')->value('current_value'));
    }

    public function test_simple_label_supports_existing_print_formats_without_metadata(): void
    {
        $this->actingAsSuperAdmin();
        $waybillNumber = $this->generateOneWaybill();
        $waybill = Waybill::query()->where('waybill_number', $waybillNumber)->firstOrFail();
        $path = '/api/waybills/'.$waybill->id.'/simple-label';

        $html = $this->get($path.'?format=html')->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $html->assertSee($waybillNumber)
            ->assertSee('<svg', false)
            ->assertDontSee('Fast Logistics')
            ->assertDontSee('Status')
            ->assertDontSee('Order')
            ->assertDontSee('Shipment');

        $this->get($path.'?format=pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $zpl = $this->get($path.'?format=zpl')->assertOk();
        $this->assertStringContainsString('^FD'.$waybillNumber.'^FS', $zpl->getContent());
        $this->assertStringNotContainsString('Status:', $zpl->getContent());

        $tspl = $this->get($path.'?format=tspl')->assertOk();
        $this->assertStringContainsString('"'.$waybillNumber.'"', $tspl->getContent());
        $this->assertStringNotContainsString('Status:', $tspl->getContent());
    }

    public function test_simple_label_requires_print_permission(): void
    {
        $this->actingAsSuperAdmin();
        $waybillId = Waybill::query()
            ->where('waybill_number', $this->generateOneWaybill())
            ->value('id');

        $staff = User::factory()->create([
            'role' => 'staff',
            'role_id' => Role::query()->where('slug', 'staff')->value('id'),
        ]);
        Sanctum::actingAs($staff);

        $this->getJson('/api/waybills/'.$waybillId.'/simple-label?format=json')->assertForbidden();

        $merchantUser = User::factory()->create([
            'role' => 'merchant_portal',
            'role_id' => Role::query()->where('slug', 'merchant_portal')->value('id'),
        ]);
        Sanctum::actingAs($merchantUser);

        $this->getJson('/api/waybills/'.$waybillId.'/simple-label?format=json')->assertForbidden();
    }

    public function test_assigned_and_void_waybills_follow_existing_reprint_policy(): void
    {
        $this->actingAsSuperAdmin();
        $assignedNumber = $this->generateOneWaybill();
        [$merchant, $governorate, $area] = $this->orderContext();

        $this->postJson('/api/orders', $this->orderPayload($merchant->id, $governorate->id, $area->id, [
            'waybill_mode' => 'preprinted',
            'preprinted_waybill_number' => $assignedNumber,
        ]))->assertCreated();
        $assigned = Waybill::query()->where('waybill_number', $assignedNumber)->firstOrFail();

        $this->getJson('/api/waybills/'.$assigned->id.'/simple-label?format=json')
            ->assertOk()
            ->assertExactJson(['data' => ['waybill_number' => $assignedNumber, 'barcode' => $assignedNumber]]);
        $this->get('/api/waybills/'.$assigned->id.'/label?format=html')->assertOk();

        $voidNumber = $this->generateOneWaybill();
        $void = Waybill::query()->where('waybill_number', $voidNumber)->firstOrFail();
        $this->postJson('/api/waybills/'.$void->id.'/void', ['reason' => 'Damaged'])->assertOk();
        $this->getJson('/api/waybills/'.$void->id.'/simple-label?format=json')
            ->assertOk()
            ->assertExactJson(['data' => ['waybill_number' => $voidNumber, 'barcode' => $voidNumber]]);

        $this->assertSame(WaybillStatus::ASSIGNED, $assigned->fresh()->status);
        $this->assertSame(WaybillStatus::VOID, $void->fresh()->status);
    }

    public function test_batch_simple_print_contains_existing_waybills_without_consuming_numbers(): void
    {
        $this->actingAsSuperAdmin();
        $batchId = $this->postJson('/api/waybill-batches', ['quantity' => 3])->assertCreated()->json('data.id');
        $numbers = Waybill::query()->orderBy('id')->pluck('waybill_number')->all();
        $sequenceBefore = NumberSequence::query()->where('key', 'waybill')->value('current_value');

        $response = $this->getJson('/api/waybill-batches/'.$batchId.'/simple-labels/print?format=json')
            ->assertOk();

        $this->assertSame($numbers, collect($response->json('data'))->pluck('waybill_number')->all());
        $this->assertSame($numbers, collect($response->json('data'))->pluck('barcode')->all());
        $this->assertSame(3, Waybill::query()->count());
        $this->assertSame($sequenceBefore, NumberSequence::query()->where('key', 'waybill')->value('current_value'));
    }

    public function test_available_preprinted_waybill_assigns_atomically_and_cannot_be_assigned_twice(): void
    {
        $this->actingAsSuperAdmin();
        $waybillNumber = $this->generateOneWaybill();
        [$merchant, $governorate, $area] = $this->orderContext();

        $this->postJson('/api/waybills/validate', ['waybill_number' => $waybillNumber])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('waybill.waybill_number', $waybillNumber);
        $this->postJson('/api/barcodes/lookup', ['barcode' => $waybillNumber])
            ->assertOk()
            ->assertJsonPath('data.type', 'waybill')
            ->assertJsonPath('data.data.waybill_number', $waybillNumber);

        $first = $this->postJson('/api/orders', $this->orderPayload($merchant->id, $governorate->id, $area->id, [
            'waybill_mode' => 'preprinted',
            'preprinted_waybill_number' => $waybillNumber,
        ]))->assertCreated();

        $waybill = Waybill::query()->where('waybill_number', $waybillNumber)->firstOrFail();
        $this->assertSame(WaybillStatus::ASSIGNED, $waybill->status);
        $this->assertSame($first->json('data.id'), $waybill->order_id);
        $this->assertSame($merchant->id, $waybill->merchant_id);

        $ordersBefore = DB::table('orders')->count();
        $this->postJson('/api/orders', $this->orderPayload($merchant->id, $governorate->id, $area->id, [
            'customer_name' => 'Second Customer',
            'customer_phone' => '01111111111',
            'waybill_mode' => 'preprinted',
            'preprinted_waybill_number' => $waybillNumber,
        ]))->assertStatus(409)->assertJsonPath('code', 'WAYBILL_ALREADY_ASSIGNED');

        $this->assertSame($ordersBefore, DB::table('orders')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'waybill_assigned')->count());
    }

    public function test_void_waybill_is_permanently_consumed_and_cannot_be_assigned(): void
    {
        $this->actingAsSuperAdmin();
        $waybillNumber = $this->generateOneWaybill();
        $waybill = Waybill::query()->where('waybill_number', $waybillNumber)->firstOrFail();

        $this->postJson('/api/waybills/'.$waybill->id.'/void', ['reason' => 'Label damaged before use'])
            ->assertOk()
            ->assertJsonPath('data.status', WaybillStatus::VOID)
            ->assertJsonPath('data.void_reason', 'Label damaged before use');

        [$merchant, $governorate, $area] = $this->orderContext();
        $this->postJson('/api/orders', $this->orderPayload($merchant->id, $governorate->id, $area->id, [
            'waybill_mode' => 'preprinted',
            'preprinted_waybill_number' => $waybillNumber,
        ]))->assertStatus(409)->assertJsonPath('code', 'WAYBILL_VOID');

        $this->postJson('/api/waybill-batches', ['quantity' => 1])
            ->assertCreated()
            ->assertJsonPath('data.first_waybill_number', 'WB-000002');
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'waybill_voided',
            'auditable_id' => $waybill->id,
        ]);
    }

    public function test_shipment_reuses_order_preprinted_waybill_and_does_not_generate_second_number(): void
    {
        $this->actingAsSuperAdmin();
        $waybillNumber = $this->generateOneWaybill();
        [$merchant, $governorate, $area] = $this->orderContext();

        $orderId = $this->postJson('/api/orders', $this->orderPayload($merchant->id, $governorate->id, $area->id, [
            'status' => OrderStatus::CONFIRMED,
            'waybill_mode' => 'preprinted',
            'preprinted_waybill_number' => $waybillNumber,
        ]))->assertCreated()->json('data.id');

        $shipmentId = $this->postJson('/api/orders/'.$orderId.'/shipments')->assertOk()->json('data.id');
        $waybill = Waybill::query()->where('waybill_number', $waybillNumber)->firstOrFail();

        $this->assertSame($shipmentId, $waybill->shipment_id);
        $this->assertSame(1, Waybill::query()->count());
        $this->assertSame(1, NumberSequence::query()->where('key', 'waybill')->value('current_value'));
    }

    public function test_existing_labels_and_public_tracking_remain_compatible_and_waybill_tracking_is_additive(): void
    {
        $this->actingAsSuperAdmin();
        [, $order] = $this->confirmedOrder();
        $shipmentId = $this->postJson('/api/orders/'.$order->id.'/shipments')->assertOk()->json('data.id');
        $waybill = Waybill::query()->where('shipment_id', $shipmentId)->firstOrFail();
        $shipment = $waybill->shipment()->firstOrFail();

        $this->get('/api/shipments/'.$shipmentId.'/waybill?format=html')
            ->assertOk()
            ->assertSee($shipment->shipment_number)
            ->assertSee($waybill->waybill_number);

        $this->getJson('/api/track/'.$shipment->shipment_number)
            ->assertOk()
            ->assertJsonPath('data.shipment_number', $shipment->shipment_number)
            ->assertJsonPath('data.waybill_number', $waybill->waybill_number);
        $this->getJson('/api/track/'.$waybill->waybill_number)
            ->assertOk()
            ->assertJsonPath('data.shipment_number', $shipment->shipment_number);
    }

    public function test_permissions_and_batch_limit_are_enforced(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'role_id' => Role::query()->where('slug', 'staff')->value('id'),
        ]);
        Sanctum::actingAs($staff);
        $this->postJson('/api/waybill-batches', ['quantity' => 1])->assertForbidden();

        $merchantUser = User::factory()->create([
            'role' => 'merchant_portal',
            'role_id' => Role::query()->where('slug', 'merchant_portal')->value('id'),
        ]);
        Sanctum::actingAs($merchantUser);
        $this->postJson('/api/waybill-batches', ['quantity' => 1])->assertForbidden();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/waybill-batches', ['quantity' => 501])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'WAYBILL_BATCH_LIMIT_EXCEEDED');
        $this->assertDatabaseCount('waybill_batches', 0);
    }

    public function test_configured_prefix_padding_and_limit_apply_only_to_future_numbers(): void
    {
        $this->actingAsSuperAdmin();
        $this->generateOneWaybill();

        SystemSetting::query()->where('key', 'waybill_prefix')->update(['value' => 'AWB-']);
        SystemSetting::query()->where('key', 'waybill_padding')->update(['value' => '4']);
        app(SystemSettingService::class)->clearCache();

        $this->postJson('/api/waybill-batches', ['quantity' => 1])
            ->assertCreated()
            ->assertJsonPath('data.first_waybill_number', 'AWB-0002');

        $this->assertDatabaseHas('waybills', ['waybill_number' => 'WB-000001']);
        $this->assertDatabaseHas('waybills', ['waybill_number' => 'AWB-0002']);
    }

    public function test_outer_order_transaction_rollback_restores_preprinted_waybill_availability(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $waybillNumber = $this->generateOneWaybill();
        [$merchant, $governorate, $area] = $this->orderContext();

        try {
            DB::transaction(function () use ($admin, $waybillNumber, $merchant, $governorate, $area): void {
                app(CreateOrderAction::class)->execute(
                    $this->orderPayload($merchant->id, $governorate->id, $area->id, [
                        'waybill_mode' => 'preprinted',
                        'preprinted_waybill_number' => $waybillNumber,
                    ]),
                    $admin->id,
                );

                throw new RuntimeException('Force outer transaction rollback.');
            });
        } catch (RuntimeException) {
            // Expected rollback.
        }

        $waybill = Waybill::query()->where('waybill_number', $waybillNumber)->firstOrFail();
        $this->assertSame(WaybillStatus::AVAILABLE, $waybill->status);
        $this->assertNull($waybill->order_id);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, DB::table('audit_logs')->where('event_type', 'waybill_assigned')->count());
    }

    private function generateOneWaybill(): string
    {
        return $this->postJson('/api/waybill-batches', ['quantity' => 1])
            ->assertCreated()
            ->json('data.first_waybill_number');
    }

    private function confirmedOrder(): array
    {
        [$merchant, $governorate, $area] = $this->orderContext();
        $order = $this->makeOrder($merchant, $governorate, $area, ['status' => OrderStatus::CONFIRMED]);

        return [$merchant, $order];
    }

    private function orderContext(): array
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();

        return [$merchant, $governorate, $area];
    }

    private function orderPayload(int $merchantId, int $governorateId, int $areaId, array $overrides = []): array
    {
        return array_merge([
            'merchant_id' => $merchantId,
            'customer_name' => 'Waybill Customer',
            'customer_phone' => '01000000000',
            'delivery_governorate_id' => $governorateId,
            'delivery_area_id' => $areaId,
            'delivery_address' => 'Cairo, Egypt',
            'shipping_fee' => 20,
            'cod_amount' => 100,
            'items' => [[
                'product_name' => 'Waybill Item',
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ], $overrides);
    }
}
