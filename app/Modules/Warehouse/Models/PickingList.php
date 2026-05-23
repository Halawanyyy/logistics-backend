<?php

namespace App\Modules\Warehouse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PickingList extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'picking_number',
        'warehouse_id',
        'assigned_to',
        'status',
        'notes',
        'created_by',
        'picked_at',
    ];

    protected function casts(): array
    {
        return [
            'picked_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PickingListItem::class);
    }

    public function packingTasks(): HasMany
    {
        return $this->hasMany(PackingTask::class);
    }
}
