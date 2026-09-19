<?php

namespace App\Modules\LocationPricing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingPlanFeature extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'pricing_plan_id',
        'label',
        'is_included',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pricing_plan_id' => 'integer',
            'is_included' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class, 'pricing_plan_id');
    }
}
