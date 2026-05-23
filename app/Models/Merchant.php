<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Modules\MerchantIntegration\Models\MerchantIntegrationToken;
use App\Modules\LocationPricing\Models\PricingPlan;
use App\Modules\Finance\Models\MerchantWalletTransaction;
use App\Modules\Warehouse\Models\WarehouseCharge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Merchant extends Model
{
    use Auditable, SoftDeletes;

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
}
