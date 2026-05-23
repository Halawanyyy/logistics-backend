<?php

namespace App\Modules\Shipment\Models;

use App\Models\User;
use App\Modules\Driver\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAttempt extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'shipment_id',
        'driver_id',
        'attempt_number',
        'attempt_date',
        'result',
        'reason',
        'notes',
        'next_action',
        'next_attempt_date',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_date' => 'datetime',
            'next_attempt_date' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
