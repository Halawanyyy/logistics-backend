<?php

namespace App\Modules\Finance\Models;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantInvoice extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'merchant_id',
        'invoice_number',
        'period_start',
        'period_end',
        'total_cod',
        'total_shipping_fees',
        'total_warehouse_charges',
        'total_payable',
        'status',
        'notes',
        'created_by',
        'issued_at',
        'file_path',
        'generated_at',
        'download_count',
        'last_downloaded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_cod' => 'decimal:2',
            'total_shipping_fees' => 'decimal:2',
            'total_warehouse_charges' => 'decimal:2',
            'total_payable' => 'decimal:2',
            'issued_at' => 'datetime',
            'generated_at' => 'datetime',
            'last_downloaded_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MerchantInvoiceItem::class);
    }
}
