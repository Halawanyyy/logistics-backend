<?php

namespace App\Modules\MerchantPortal\Models;

use App\Models\Merchant;
use App\Models\User;
use App\Modules\MerchantPortal\Enums\MerchantPortalUserStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantUser extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'merchant_id',
        'user_id',
        'role',
        'status',
        'is_primary',
        'invited_by',
        'invited_at',
        'accepted_at',
        'last_login_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isActive(): bool
    {
        return $this->status === MerchantPortalUserStatus::ACTIVE && $this->deleted_at === null;
    }
}
