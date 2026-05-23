<?php

namespace App\Modules\Warehouse\Models;

use App\Models\Merchant;
use App\Modules\LocationPricing\Models\PricingPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StorageFeeRule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'pricing_plan_id',
        'name',
        'calculation_type',
        'free_units',
        'unit_price',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'free_units' => 'integer',
            'unit_price' => 'decimal:2',
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
