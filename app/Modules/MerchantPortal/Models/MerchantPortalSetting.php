<?php

namespace App\Modules\MerchantPortal\Models;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantPortalSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'merchant_id',
        'default_fulfillment_type',
        'default_payment_type',
        'default_pickup_address',
        'default_label_format',
        'notification_preferences',
        'integration_preferences',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notification_preferences' => 'array',
            'integration_preferences' => 'array',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
