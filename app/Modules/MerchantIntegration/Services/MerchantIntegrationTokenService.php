<?php

namespace App\Modules\MerchantIntegration\Services;

use App\Models\Merchant;
use App\Models\User;
use App\Modules\MerchantIntegration\Models\MerchantIntegrationToken;
use App\Modules\Settings\Services\SystemSettingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MerchantIntegrationTokenService
{
    public function __construct(
        private readonly SystemSettingService $systemSettingService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{model: MerchantIntegrationToken, plain_token: string}
     */
    public function generateToken(Merchant $merchant, array $data, User $user): array
    {
        $plainToken = $this->makePlainToken();

        $model = MerchantIntegrationToken::query()->create([
            'merchant_id' => $merchant->id,
            'name' => $data['name'],
            'token_hash' => $this->hashToken($plainToken),
            'token_prefix' => $this->tokenPrefix($plainToken),
            'provider' => $data['provider'] ?? MerchantIntegrationToken::PROVIDER_CUSTOM,
            'permissions' => $this->normalizedPermissions($data['permissions'] ?? null),
            'status' => MerchantIntegrationToken::STATUS_ACTIVE,
            'created_by' => $user->id,
            'notes' => $data['notes'] ?? null,
            'expires_at' => $this->resolveExpiry($data['expires_at'] ?? null),
        ]);

        return [
            'model' => $model->load(['merchant', 'createdBy', 'revokedBy']),
            'plain_token' => $plainToken,
        ];
    }

    public function verifyToken(string $plainToken): ?MerchantIntegrationToken
    {
        $token = $this->findByPlainToken($plainToken);

        if (! $token instanceof MerchantIntegrationToken) {
            return null;
        }

        if ($token->status === MerchantIntegrationToken::STATUS_REVOKED || $token->revoked_at !== null) {
            return null;
        }

        if ($token->isExpired()) {
            if ($token->status !== MerchantIntegrationToken::STATUS_EXPIRED) {
                $token->update(['status' => MerchantIntegrationToken::STATUS_EXPIRED]);
            }

            return null;
        }

        if (! $token->merchant || $token->merchant->status !== 'active' || $token->status !== MerchantIntegrationToken::STATUS_ACTIVE) {
            return null;
        }

        $this->touchLastUsed($token);

        return $token->fresh(['merchant', 'createdBy', 'revokedBy']);
    }

    public function revokeToken(MerchantIntegrationToken $token, User $user, ?string $notes = null): MerchantIntegrationToken
    {
        $token->revoke($user, $notes);

        return $token->fresh(['merchant', 'createdBy', 'revokedBy']);
    }

    public function touchLastUsed(MerchantIntegrationToken $token): void
    {
        $token->forceFill([
            'last_used_at' => now(),
        ])->save();
    }

    public function findByPlainToken(string $plainToken): ?MerchantIntegrationToken
    {
        return MerchantIntegrationToken::query()
            ->with(['merchant', 'createdBy', 'revokedBy'])
            ->where('token_hash', $this->hashToken($plainToken))
            ->first();
    }

    /**
     * @param  list<string>|null  $permissions
     * @return list<string>
     */
    public function normalizedPermissions(?array $permissions): array
    {
        $permissions = $permissions === null || $permissions === []
            ? MerchantIntegrationToken::DEFAULT_PERMISSIONS
            : array_values(array_unique($permissions));

        return array_values(array_intersect($permissions, MerchantIntegrationToken::SUPPORTED_PERMISSIONS));
    }

    private function makePlainToken(): string
    {
        return 'mit_live_'.Str::lower(bin2hex(random_bytes(24)));
    }

    private function tokenPrefix(string $plainToken): string
    {
        return substr($plainToken, 0, 16).'...';
    }

    private function hashToken(string $plainToken): string
    {
        return hash_hmac('sha256', $plainToken, (string) config('app.key', 'merchant-integration-token'));
    }

    private function resolveExpiry(mixed $expiresAt): ?Carbon
    {
        if ($expiresAt !== null) {
            return Carbon::parse($expiresAt);
        }

        $days = $this->systemSettingService->get('integration_tokens_default_expiry_days');

        if ($days === null || $days === '') {
            return null;
        }

        $days = (int) $days;

        return $days > 0 ? now()->addDays($days) : null;
    }
}
