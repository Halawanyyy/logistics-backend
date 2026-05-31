<?php

namespace App\Modules\Warehouse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'warehouse_id',
        'warehouse_product_id',
        'order_id',
        'shipment_id',
        'type',
        'quantity',
        'before_available',
        'after_available',
        'before_reserved',
        'after_reserved',
        'before_damaged',
        'after_damaged',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseProduct(): BelongsTo
    {
        return $this->belongsTo(WarehouseProduct::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Order\Models\Order::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Shipment\Models\Shipment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
