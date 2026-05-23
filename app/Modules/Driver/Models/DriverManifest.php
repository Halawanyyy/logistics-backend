<?php

namespace App\Modules\Driver\Models;

use App\Models\User;
use App\Modules\Finance\Models\DriverCashClosure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriverManifest extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'manifest_number',
        'driver_id',
        'manifest_date',
        'status',
        'total_shipments',
        'expected_cod_amount',
        'actual_received_amount',
        'difference_amount',
        'notes',
        'created_by',
        'closed_by',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'manifest_date' => 'date',
            'expected_cod_amount' => 'decimal:2',
            'actual_received_amount' => 'decimal:2',
            'difference_amount' => 'decimal:2',
            'closed_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DriverManifestItem::class);
    }

    public function cashClosure(): HasOne
    {
        return $this->hasOne(DriverCashClosure::class, 'driver_manifest_id');
    }
}
