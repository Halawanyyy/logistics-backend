<?php

namespace App\Modules\Shipment\Models;

use App\Models\Merchant;
use App\Models\User;
use App\Traits\Auditable;
use App\Modules\Driver\Models\DriverManifestItem;
use App\Modules\Driver\Models\Driver;
use App\Modules\LocationPricing\Models\Area;
use App\Modules\LocationPricing\Models\Governorate;
use App\Modules\Order\Enums\OrderType;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use Auditable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'shipment_number',
        'barcode',
        'merchant_id',
        'customer_name',
        'customer_phone',
        'delivery_governorate_id',
        'delivery_area_id',
        'delivery_address',
        'cod_amount',
        'shipping_fee',
        'status',
        'tracking_notes',
        'created_by',
        'assigned_driver_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cod_amount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'deleted_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function deliveryGovernorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class, 'delivery_governorate_id');
    }

    public function deliveryArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'delivery_area_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ShipmentStatusHistory::class)->orderBy('id');
    }

    public function itemResults(): HasMany
    {
        return $this->hasMany(ShipmentItemResult::class);
    }

    public function manifestItems(): HasMany
    {
        return $this->hasMany(DriverManifestItem::class);
    }

    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class)->orderBy('attempt_number');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFinanciallyCompleted(Builder $query): Builder
    {
        return $query->where(function (Builder $completionQuery): void {
            $completionQuery
                ->whereIn('status', ShipmentStatus::financiallyCompletedValues())
                ->orWhere(function (Builder $returnQuery): void {
                    $returnQuery
                        ->whereIn('status', [
                            ShipmentStatus::RETURNED,
                            ShipmentStatus::RETURNED_TO_HUB,
                            ShipmentStatus::RETURNED_TO_WAREHOUSE,
                        ])
                        ->whereHas('order', fn (Builder $orderQuery) => $orderQuery->where('order_type', OrderType::RETURN));
                });
        });
    }

    public function getDeliveredCodAmountAttribute(): string
    {
        $order = $this->relationLoaded('order')
            ? $this->order
            : $this->order()->with('items')->first();

        if ($order === null) {
            return number_format(0, 2, '.', '');
        }

        if ($order->relationLoaded('items')) {
            return $order->delivered_cod_amount;
        }

        $items = $order->items()->get(['order_id', 'delivered_quantity', 'unit_price']);
        $amount = $items->sum(fn (OrderItem $item): float => (float) $item->unit_price * (int) $item->delivered_quantity);

        return number_format($amount, 2, '.', '');
    }
}
