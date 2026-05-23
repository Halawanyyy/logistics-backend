<?php

namespace App\Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStock extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'warehouse_id',
        'warehouse_product_id',
        'warehouse_bin_id',
        'quantity_available',
        'quantity_reserved',
        'quantity_damaged',
    ];

    protected function casts(): array
    {
        return [
            'quantity_available' => 'integer',
            'quantity_reserved' => 'integer',
            'quantity_damaged' => 'integer',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
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
