<?php

namespace App\Modules\Order\Models;

use App\Models\Merchant;
use App\Models\User;
use App\Traits\Auditable;
use App\Modules\LocationPricing\Models\Area;
use App\Modules\LocationPricing\Models\Governorate;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Warehouse\Models\OrderStockReservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use Auditable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected array $auditExcluded = ['barcode'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'merchant_id',
        'order_number',
        'barcode',
        'customer_name',
        'customer_phone',
        'customer_phone_alt',
        'delivery_governorate_id',
        'delivery_area_id',
        'delivery_address',
        'delivery_notes',
        'pickup_governorate_id',
        'pickup_area_id',
        'pickup_address',
        'pickup_notes',
        'cod_amount',
        'shipping_fee',
        'order_type',
        'original_order_id',
        'payment_type',
        'fulfillment_type',
        'warehouse_fulfillment_status',
        'is_fragile',
        'allow_inspection',
        'requires_packaging',
        'package_notes',
        'source',
        'external_source',
        'external_order_id',
        'external_order_number',
        'requires_review',
        'review_reason',
        'status',
        'notes',
        'swap_notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cod_amount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'is_fragile' => 'boolean',
            'allow_inspection' => 'boolean',
            'requires_packaging' => 'boolean',
            'requires_review' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveryGovernorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class, 'delivery_governorate_id');
    }

    public function deliveryArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'delivery_area_id');
    }

    public function pickupGovernorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class, 'pickup_governorate_id');
    }

    public function pickupArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'pickup_area_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function originalOrder(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_order_id');
    }

    public function swapOrders(): HasMany
    {
        return $this->hasMany(self::class, 'original_order_id');
    }

    public function stockReservations(): HasMany
    {
        return $this->hasMany(OrderStockReservation::class);
    }

    public function latestStockReservation(): HasOne
    {
        return $this->hasOne(OrderStockReservation::class)->latestOfMany();
    }

    public function getDeliveredCodAmountAttribute(): string
    {
        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->get(['order_id', 'delivered_quantity', 'unit_price']);

        $amount = $items->sum(fn (OrderItem $item): float => (float) $item->unit_price * (int) $item->delivered_quantity);

        return number_format($amount, 2, '.', '');
    }
}
