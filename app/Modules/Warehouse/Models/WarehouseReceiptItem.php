<?php

namespace App\Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseReceiptItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'warehouse_receipt_id',
        'warehouse_product_id',
        'quantity',
        'accepted_quantity',
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
            'quantity' => 'integer',
            'accepted_quantity' => 'integer',
            'rejected_quantity' => 'integer',
        ];
    }

    public function warehouseReceipt(): BelongsTo
    {
        return $this->belongsTo(WarehouseReceipt::class);
    }

    public function warehouseProduct(): BelongsTo
    {
        return $this->belongsTo(WarehouseProduct::class);
    }
}
