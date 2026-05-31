<?php

namespace App\Modules\MerchantPortal\Models;

use App\Models\Merchant;
use App\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseReceipt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantInboundStockRequest extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_number',
        'merchant_id',
        'warehouse_id',
        'status',
        'requested_by',
        'reviewed_by',
        'linked_warehouse_receipt_id',
        'expected_arrival_date',
        'submitted_at',
        'cancelled_at',
        'received_at',
        'closed_at',
        'notes',
        'internal_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_arrival_date' => 'date',
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'received_at' => 'datetime',
            'closed_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function linkedWarehouseReceipt(): BelongsTo
    {
        return $this->belongsTo(WarehouseReceipt::class, 'linked_warehouse_receipt_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MerchantInboundStockRequestItem::class, 'inbound_request_id');
    }
}
