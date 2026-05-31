<?php

namespace App\Modules\MerchantIntegration\Models;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantIntegrationToken extends Model
{
    use SoftDeletes;

    public const PROVIDER_WOOCOMMERCE = 'woocommerce';
    public const PROVIDER_SHOPIFY = 'shopify';
    public const PROVIDER_CUSTOM = 'custom';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';

    /**
     * @var list<string>
     */
    public const SUPPORTED_PERMISSIONS = [
        'integrations.me',
        'orders.create',
        'orders.view',
        'shipments.view',
        'tracking.view',
        'products.view',
        'inventory.view',
    ];

    /**
     * @var list<string>
     */
    public const DEFAULT_PERMISSIONS = [
        'orders.create',
        'orders.view',
        'shipments.view',
        'tracking.view',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'merchant_id',
        'name',
        'token_hash',
        'token_prefix',
        'provider',
        'permissions',
        'status',
        'last_used_at',
        'revoked_at',
        'revoked_by',
        'created_by',
        'notes',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->revoked_at === null
            && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    public function revoke(?User $user = null, ?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
            'revoked_by' => $user?->id,
            'notes' => $notes !== null
                ? trim(($this->notes ? $this->notes.PHP_EOL : '').$notes)
                : $this->notes,
        ]);
    }

    /**
     * @return list<string>
     */
    public static function providerValues(): array
    {
        return [
            self::PROVIDER_WOOCOMMERCE,
            self::PROVIDER_SHOPIFY,
            self::PROVIDER_CUSTOM,
        ];
    }

    /**
     * @return list<string>
     */
    public static function statusValues(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_REVOKED,
            self::STATUS_EXPIRED,
        ];
    }
}
