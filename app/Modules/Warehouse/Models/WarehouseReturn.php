<?php

namespace App\Modules\Warehouse\Models;

use App\Models\User;
use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Models\Shipment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseReturn extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shipment_id',
        'order_id',
        'warehouse_id',
        'warehouse_product_id',
        'quantity',
        'status',
        'condition',
        'notes',
        'created_by',
        'received_by',
        'inspected_by',
        'restocked_by',
        'damaged_by',
        'rejected_by',
        'closed_by',
        'received_at',
        'inspected_at',
        'restocked_at',
        'damaged_at',
        'rejected_at',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'inspected_at' => 'datetime',
            'restocked_at' => 'datetime',
            'damaged_at' => 'datetime',
            'rejected_at' => 'datetime',
            'closed_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseProduct(): BelongsTo
    {
        return $this->belongsTo(WarehouseProduct::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WarehouseReturnItem::class);
    }
}
