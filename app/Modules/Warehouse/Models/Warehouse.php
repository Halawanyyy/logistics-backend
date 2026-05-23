<?php

namespace App\Modules\Warehouse\Models;

use App\Modules\LocationPricing\Models\Area;
use App\Modules\LocationPricing\Models\Governorate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'address',
        'governorate_id',
        'area_id',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
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

    public function zones(): HasMany
    {
        return $this->hasMany(WarehouseZone::class);
    }

    public function shelves(): HasMany
    {
        return $this->hasMany(WarehouseShelf::class);
    }

    public function bins(): HasMany
    {
        return $this->hasMany(WarehouseBin::class);
    }
}
