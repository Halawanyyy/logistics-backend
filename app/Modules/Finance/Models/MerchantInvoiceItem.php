<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantInvoiceItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'merchant_invoice_id',
        'item_type',
        'description',
        'quantity',
        'unit_amount',
        'total_amount',
        'source_type',
        'source_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(MerchantInvoice::class, 'merchant_invoice_id');
    }
}
