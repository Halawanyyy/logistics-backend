<?php

namespace App\Modules\Warehouse\Models;

use App\Models\Merchant;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseProduct extends Model
{
    use Auditable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'merchant_id',
        'name',
        'sku',
        'barcode',
        'description',
        'unit_weight',
        'unit_length',
        'unit_width',
        'unit_height',
        'is_fragile',
        'requires_packaging',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_weight' => 'decimal:2',
            'unit_length' => 'decimal:2',
            'unit_width' => 'decimal:2',
            'unit_height' => 'decimal:2',
            'is_fragile' => 'boolean',
            'requires_packaging' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function inventoryStocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function orderStockReservations(): HasMany
    {
        return $this->hasMany(OrderStockReservation::class);
    }

    public function warehouseReturns(): HasMany
    {
        return $this->hasMany(WarehouseReturn::class);
    }

    public function warehouseCharges(): HasMany
    {
        return $this->hasMany(WarehouseCharge::class);
    }
}
