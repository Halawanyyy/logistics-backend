<?php

namespace App\Modules\Warehouse\Models;

use App\Models\User;
use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Models\Shipment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PackingTask extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'packing_number',
        'picking_list_id',
        'order_id',
        'shipment_id',
        'status',
        'package_weight',
        'package_length',
        'package_width',
        'package_height',
        'packaging_fee',
        'notes',
        'packed_by',
        'packed_at',
    ];

    protected function casts(): array
    {
        return [
            'package_weight' => 'decimal:2',
            'package_length' => 'decimal:2',
            'package_width' => 'decimal:2',
            'package_height' => 'decimal:2',
            'packaging_fee' => 'decimal:2',
            'packed_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function pickingList(): BelongsTo
    {
        return $this->belongsTo(PickingList::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function packedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'packed_by');
    }
}
