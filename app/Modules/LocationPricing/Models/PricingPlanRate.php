<?php

namespace App\Modules\LocationPricing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingPlanRate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'pricing_plan_id',
        'governorate_id',
        'area_id',
        'delivery_fee',
        'swap_fee',
        'return_fee',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_fee' => 'decimal:2',
            'swap_fee' => 'decimal:2',
            'return_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function pricingPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
