<?php

namespace App\Models;

use App\Modules\Attachment\Models\Attachment;
use App\Modules\Export\Models\ExportLog;
use App\Modules\Finance\Models\MerchantWalletTransaction;
use App\Modules\LocationPricing\Models\PricingPlan;
use App\Modules\MerchantIntegration\Models\MerchantIntegrationToken;
use App\Modules\MerchantPortal\Models\MerchantNotification;
use App\Modules\MerchantPortal\Models\MerchantPortalSetting;
use App\Modules\MerchantPortal\Models\MerchantUser;
use App\Modules\Warehouse\Models\WarehouseCharge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Merchant extends Model
{
    use Auditable, SoftDeletes;

    /** @use HasFactory<MerchantFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'company_name',
        'phone',
        'email',
        'address',
        'status',
        'pricing_plan_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public function warehouseCharges(): HasMany
    {
        return $this->hasMany(WarehouseCharge::class);
    }

    public function pricingPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(MerchantWalletTransaction::class);
    }

    public function integrationTokens(): HasMany
    {
        return $this->hasMany(MerchantIntegrationToken::class);
    }

    public function merchantUsers(): HasMany
    {
        return $this->hasMany(MerchantUser::class);
    }

    public function activeMerchantUsers(): HasMany
    {
        return $this->hasMany(MerchantUser::class)
            ->whereNull('deleted_at')
            ->where('status', 'active');
    }

    public function portalSetting()
    {
        return $this->hasOne(MerchantPortalSetting::class);
    }

    public function portalNotifications(): HasMany
    {
        return $this->hasMany(MerchantNotification::class);
    }

    public function portalExports(): HasMany
    {
        return $this->hasMany(ExportLog::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
