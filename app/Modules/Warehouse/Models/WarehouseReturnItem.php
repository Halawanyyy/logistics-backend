<?php

namespace App\Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseReturnItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'warehouse_return_id',
        'warehouse_product_id',
        'expected_quantity',
        'received_quantity',
        'restock_quantity',
        'damaged_quantity',
        'rejected_quantity',
        'condition',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_quantity' => 'integer',
            'received_quantity' => 'integer',
            'restock_quantity' => 'integer',
            'damaged_quantity' => 'integer',
            'rejected_quantity' => 'integer',
        ];
    }

    public function warehouseReturn(): BelongsTo
    {
        return $this->belongsTo(WarehouseReturn::class);
    }

    public function warehouseProduct(): BelongsTo
    {
        return $this->belongsTo(WarehouseProduct::class);
    }
}
