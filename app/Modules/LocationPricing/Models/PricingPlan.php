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
            'deleted_at' => 'datetime',
        ];
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
