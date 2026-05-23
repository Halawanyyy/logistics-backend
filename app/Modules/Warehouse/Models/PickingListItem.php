<?php

namespace App\Modules\Warehouse\Models;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickingListItem extends Model
{
    protected $fillable = [
        'picking_list_id',
        'order_id',
        'order_item_id',
        'warehouse_product_id',
        'warehouse_bin_id',
        'quantity_required',
        'quantity_picked',
        'status',
    ];

    public function pickingList(): BelongsTo
    {
        return $this->belongsTo(PickingList::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function warehouseProduct(): BelongsTo
    {
        return $this->belongsTo(WarehouseProduct::class);
    }

    public function warehouseBin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class);
    }
}
