<?php

namespace App\Modules\Warehouse\Models;

use App\Models\Merchant;
use App\Modules\LocationPricing\Models\PricingPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PickPackFeeRule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'pricing_plan_id',
        'name',
        'pick_fee_first_item',
        'pick_fee_additional_item',
        'packing_fee_base',
        'fragile_extra_fee',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'pick_fee_first_item' => 'decimal:2',
            'pick_fee_additional_item' => 'decimal:2',
            'packing_fee_base' => 'decimal:2',
            'fragile_extra_fee' => 'decimal:2',
            'deleted_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function pricingPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class);
    }
}
