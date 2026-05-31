<?php

namespace App\Modules\MerchantPortal\Models;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantNotification extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'merchant_id',
        'merchant_user_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function merchantUser(): BelongsTo
    {
        return $this->belongsTo(MerchantUser::class);
    }
}
