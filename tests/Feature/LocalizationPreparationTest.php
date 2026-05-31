<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\MerchantPortal\Enums\MerchantPortalRole;
use App\Modules\MerchantPortal\Enums\MerchantPortalUserStatus;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Shipment\Models\ShipmentStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class LocalizationPreparationTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_default_locale_returns_english_public_labels_and_messages(): void
    {
        $shipment = $this->makeTrackedShipment();

        $this->getJson('/api/auth/ping')
            ->assertOk()
            ->assertJsonPath('message', 'Auth module is working');

        $this->getJson('/api/track/'.$shipment->shipment_number)
            ->assertOk()
            ->assertJsonPath('data.status', ShipmentStatus::IN_TRANSIT)
            ->assertJsonPath('data.status_label', 'In Transit');
    }

    public function test_accept_language_ar_localizes_messages_validation_and_labels(): void
    {
        $user = User::factory()->create([
            'email' => 'localization@example.com',
            'password' => Hash::make('secret123'),
        ]);
        $shipment = $this->makeTrackedShipment();

        $this->withHeader('Accept-Language', 'ar-EG')
            ->getJson('/api/auth/ping')
            ->assertOk()
            ->assertJsonPath('message', 'وحدة المصادقة تعمل');

        $this->withHeader('Accept-Language', 'ar-EG')
            ->postJson('/api/auth/login', [
                'email' => 'localization@example.com',
                'password' => 'secret123',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'تم تسجيل الدخول بنجاح')
            ->assertJsonPath('user.id', $user->id);

        $validationResponse = $this->withHeader('Accept-Language', 'ar-EG')
            ->postJson('/api/auth/login', []);

        $validationResponse
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);

        $this->assertStringContainsString(
            'البريد الإلكتروني',
            (string) $validationResponse->json('errors.email.0')
        );

        $this->withHeader('Accept-Language', 'ar-EG')
            ->getJson('/api/track/'.$shipment->shipment_number)
            ->assertOk()
            ->assertJsonPath('data.status', ShipmentStatus::IN_TRANSIT)
            ->assertJsonPath('data.status_label', 'قيد النقل')
            ->assertJsonPath('data.latest_tracking_event.status', ShipmentStatus::IN_TRANSIT)
            ->assertJsonPath('data.latest_tracking_event.status_label', 'قيد النقل');
    }

    public function test_x_locale_overrides_accept_language_and_unsupported_locale_falls_back(): void
    {
        $shipment = $this->makeTrackedShipment();

        $this->withHeaders([
            'Accept-Language' => 'en-US',
            'X-Locale' => 'ar',
        ])->getJson('/api/track/'.$shipment->shipment_number)
            ->assertOk()
            ->assertJsonPath('data.status_label', 'قيد النقل');

        $this->flushHeaders()
            ->withHeader('Accept-Language', 'fr-FR')
            ->getJson('/api/track/'.$shipment->shipment_number)
            ->assertOk()
            ->assertJsonPath('data.status_label', 'In Transit');
    }

    public function test_internal_resources_include_localized_label_fields_without_changing_raw_values(): void
    {
        $this->actingAsSuperAdmin();

        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $order = $this->makeOrder($merchant, $governorate, $area, [
            'status' => 'confirmed',
            'payment_type' => 'cod',
            'fulfillment_type' => 'pickup_from_merchant',
            'warehouse_fulfillment_status' => 'pending_reservation',
        ]);
        $shipment = $this->makeShipment($order, [
            'status' => ShipmentStatus::IN_TRANSIT,
        ]);

        $this->withHeader('Accept-Language', 'ar')
            ->getJson('/api/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.status_label', 'مؤكد')
            ->assertJsonPath('data.payment_type', 'cod')
            ->assertJsonPath('data.payment_type_label', 'الدفع عند الاستلام')
            ->assertJsonPath('data.fulfillment_type', 'pickup_from_merchant')
            ->assertJsonPath('data.fulfillment_type_label', 'استلام من التاجر')
            ->assertJsonPath('data.warehouse_fulfillment_status', 'pending_reservation')
            ->assertJsonPath('data.warehouse_fulfillment_status_label', 'بانتظار الحجز');

        $this->withHeader('Accept-Language', 'ar')
            ->getJson('/api/shipments/'.$shipment->id)
            ->assertOk()
            ->assertJsonPath('data.status', ShipmentStatus::IN_TRANSIT)
            ->assertJsonPath('data.status_label', 'قيد النقل');
    }

    public function test_merchant_portal_me_response_exposes_localized_role_and_status_labels(): void
    {
        $merchant = $this->makeMerchant();
        $user = User::factory()->create(['role' => 'merchant_portal']);

        $this->makeMerchantPortalUser($merchant, $user, [
            'role' => MerchantPortalRole::OWNER,
            'status' => MerchantPortalUserStatus::ACTIVE,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->withHeader('Accept-Language', 'ar')
            ->getJson('/api/merchant-portal/me')
            ->assertOk()
            ->assertJsonPath('data.portal_role', MerchantPortalRole::OWNER)
            ->assertJsonPath('data.portal_role_label', 'المالك')
            ->assertJsonPath('data.portal_status', MerchantPortalUserStatus::ACTIVE)
            ->assertJsonPath('data.portal_status_label', 'نشط');
    }

    public function test_public_tracking_stays_redacted_in_arabic_locale(): void
    {
        $shipment = $this->makeTrackedShipment();

        $response = $this->withHeader('Accept-Language', 'ar')
            ->getJson('/api/track/'.$shipment->shipment_number);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', ShipmentStatus::IN_TRANSIT)
            ->assertJsonPath('data.status_label', 'قيد النقل')
            ->assertJsonMissingPath('data.customer_name')
            ->assertJsonMissingPath('data.customer_phone')
            ->assertJsonMissingPath('data.delivery_address')
            ->assertJsonMissingPath('data.latest_tracking_event.notes')
            ->assertJsonMissingPath('data.tracking_events.0.notes');
    }

    private function makeTrackedShipment(): Shipment
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $order = $this->makeOrder($merchant, $governorate, $area, [
            'customer_name' => 'Localized Customer',
            'delivery_address' => 'Sensitive Address',
        ]);
        $shipment = $this->makeShipment($order, [
            'status' => ShipmentStatus::IN_TRANSIT,
            'customer_name' => 'Localized Customer',
            'delivery_address' => 'Sensitive Address',
        ]);

        ShipmentStatusHistory::create([
            'shipment_id' => $shipment->id,
            'status' => ShipmentStatus::PENDING_PICKUP,
            'notes' => 'Internal note',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        ShipmentStatusHistory::create([
            'shipment_id' => $shipment->id,
            'status' => ShipmentStatus::IN_TRANSIT,
            'notes' => 'Another internal note',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        return $shipment->fresh();
    }
}
