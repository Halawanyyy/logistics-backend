<?php

namespace App\Modules\LocationPricing\Models;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PricingPlan extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'monthly_orders_from',
        'monthly_orders_to',
        'monthly_price',
        'yearly_price',
        'currency',
        'features',
        'is_popular',
        'is_custom_pricing',
        'requires_contact',
        'display_order',
        'is_visible_to_merchants',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_orders_from' => 'integer',
            'monthly_orders_to' => 'integer',
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'is_popular' => 'boolean',
            'is_custom_pricing' => 'boolean',
            'requires_contact' => 'boolean',
            'display_order' => 'integer',
            'is_visible_to_merchants' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function getRequiresContactAttribute(): bool
    {
        return (bool) ($this->attributes['requires_contact'] ?? $this->attributes['is_custom_pricing'] ?? false);
    }

    public function setRequiresContactAttribute($value): void
    {
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $this->attributes['requires_contact'] = $bool;
        $this->attributes['is_custom_pricing'] = $bool;
    }

    public function setIsCustomPricingAttribute($value): void
    {
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $this->attributes['is_custom_pricing'] = $bool;
        $this->attributes['requires_contact'] = $bool;
    }

    public function features(): HasMany
    {
        return $this->hasMany(PricingPlanFeature::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getFeaturesAttribute()
    {
        if ($this->relationLoaded('features')) {
            return $this->getRelation('features');
        }

        return $this->features()->get();
    }

    public function rates(): HasMany
    {
        return $this->hasMany(PricingPlanRate::class);
    }

    public function merchants(): HasMany
    {
        return $this->hasMany(Merchant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
