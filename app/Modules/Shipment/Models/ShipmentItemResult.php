<?php

namespace App\Modules\Shipment\Models;

use App\Models\User;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Warehouse\Models\WarehouseProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentItemResult extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'shipment_id',
        'order_item_id',
        'warehouse_product_id',
        'result_status',
        'quantity',
        'return_condition',
        'notes',
        'created_by',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function warehouseProduct(): BelongsTo
    {
        return $this->belongsTo(WarehouseProduct::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
