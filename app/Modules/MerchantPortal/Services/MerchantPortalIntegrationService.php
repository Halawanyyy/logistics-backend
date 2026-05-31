<?php

namespace App\Modules\MerchantPortal\Services;

use App\Models\Merchant;
use App\Models\User;
use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\MerchantIntegration\Models\MerchantIntegrationToken;
use App\Modules\MerchantIntegration\Services\MerchantIntegrationTokenService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;

class MerchantPortalIntegrationService
{
    /**
     * @var list<string>
     */
    public const ALLOWED_ABILITIES = [
        'integrations.me',
        'orders.create',
        'orders.view',
        'shipments.view',
        'tracking.view',
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly MerchantIntegrationTokenService $merchantIntegrationTokenService,
        private readonly MerchantPortalNotificationService $merchantPortalNotificationService,
    ) {
    }

    public function queryForMerchant(int $merchantId): Builder
    {
        return MerchantIntegrationToken::query()
            ->where('merchant_id', $merchantId)
            ->latest();
    }

    public function findOwned(int $merchantId, int $tokenId): MerchantIntegrationToken
    {
        return $this->queryForMerchant($merchantId)->whereKey($tokenId)->firstOrFail();
    }

    /**
     * @param array<string, mixed> $validated
     * @return array{model: MerchantIntegrationToken, plain_token: string}
     */
    public function createForMerchant(Merchant $merchant, array $validated, User $user): array
    {
        $abilities = $validated['abilities'] ?? MerchantIntegrationToken::DEFAULT_PERMISSIONS;
        $invalid = array_values(array_diff($abilities, self::ALLOWED_ABILITIES));

        if ($invalid !== []) {
            $this->fail('abilities', 'One or more integration abilities are not allowed for merchant portal tokens.');
        }

        $result = $this->merchantIntegrationTokenService->generateToken($merchant, [
            'name' => $validated['name'],
            'expires_at' => $validated['expires_at'] ?? null,
            'permissions' => $abilities,
            'notes' => $validated['notes'] ?? null,
        ], $user);

        $this->merchantPortalNotificationService->create(
            $merchant->id,
            null,
            'integration_token_created',
            'Integration token created',
            'A merchant integration token was created.',
            [
                'token_id' => $result['model']->id,
                'name' => $result['model']->name,
            ],
        );

        $this->auditLogService->log(
            AuditEventType::MERCHANT_INTEGRATION_TOKEN_CREATED,
            'created',
            $result['model'],
            null,
            [
                'user' => $user,
                'metadata' => [
                    'merchant_id' => $merchant->id,
                    'provider' => $result['model']->provider,
                    'token_prefix' => $result['model']->token_prefix,
                    'permissions' => $result['model']->permissions,
                    'source' => 'merchant_portal',
                ],
                'notes' => 'Merchant portal integration token created.',
            ],
        );

        return $result;
    }

    public function revoke(MerchantIntegrationToken $token, User $user, ?string $notes = null): MerchantIntegrationToken
    {
        $revoked = $this->merchantIntegrationTokenService->revokeToken($token, $user, $notes);

        $this->merchantPortalNotificationService->create(
            $revoked->merchant_id,
            null,
            'integration_token_revoked',
            'Integration token revoked',
            'A merchant integration token was revoked.',
            [
                'token_id' => $revoked->id,
                'name' => $revoked->name,
            ],
        );

        $this->auditLogService->log(
            AuditEventType::MERCHANT_INTEGRATION_TOKEN_REVOKED,
            'revoked',
            $revoked,
            null,
            [
                'user' => $user,
                'metadata' => [
                    'merchant_id' => $revoked->merchant_id,
                    'provider' => $revoked->provider,
                    'token_prefix' => $revoked->token_prefix,
                    'source' => 'merchant_portal',
                ],
                'notes' => $notes ?? 'Merchant portal integration token revoked.',
            ],
        );

        return $revoked;
    }

    public function destroy(MerchantIntegrationToken $token, ?User $user = null): void
    {
        $this->auditLogService->log(
            AuditEventType::MERCHANT_INTEGRATION_TOKEN_DELETED,
            'deleted',
            $token,
            null,
            [
                'user' => $user,
                'metadata' => [
                    'merchant_id' => $token->merchant_id,
                    'provider' => $token->provider,
                    'token_prefix' => $token->token_prefix,
                    'source' => 'merchant_portal',
                ],
                'notes' => 'Merchant portal integration token deleted.',
            ],
        );

        $token->delete();
    }

    /**
     * @return list<string>
     */
    public function allowedAbilities(): array
    {
        return self::ALLOWED_ABILITIES;
    }

    /**
     * @return array<string, mixed>
     */
    public function docs(): array
    {
        return [
            'base_path' => '/api/integrations',
            'auth_header' => 'Authorization: Bearer {plain_token}',
            'abilities' => self::ALLOWED_ABILITIES,
            'create_order_example' => [
                'external_source' => 'woocommerce',
                'external_order_id' => 'WC-12345',
                'external_order_number' => '12345',
                'customer_name' => 'Ahmed Adel',
                'customer_phone' => '01000000000',
                'delivery_governorate_id' => 1,
                'delivery_area_id' => 1,
                'delivery_address' => 'Nasr City, Cairo',
                'payment_type' => 'cod',
                'cod_amount' => 500,
                'items' => [
                    [
                        'product_name' => 'T-Shirt',
                        'sku' => 'TSHIRT-001',
                        'quantity' => 1,
                        'unit_price' => 500,
                    ],
                ],
            ],
            'notes' => [
                'merchant_id is derived from the integration token and cannot be spoofed',
                'duplicate external_source + external_order_id pairs are blocked per merchant',
                'webhook support can be added in a future phase',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function testPayload(array $validated): array
    {
        $abilities = $validated['abilities'] ?? self::ALLOWED_ABILITIES;
        $invalid = array_values(array_diff($abilities, self::ALLOWED_ABILITIES));

        if ($invalid !== []) {
            $this->fail('abilities', 'One or more integration abilities are not allowed for merchant portal tokens.');
        }

        return [
            'ok' => true,
            'validated_abilities' => array_values($abilities),
            'base_path' => '/api/integrations',
        ];
    }

    private function fail(string $field, string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => [
                $field => [$message],
            ],
        ], 422));
    }
}
