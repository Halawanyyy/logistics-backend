<?php

namespace App\Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseZone extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'warehouse_id',
        'name',
        'code',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
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
