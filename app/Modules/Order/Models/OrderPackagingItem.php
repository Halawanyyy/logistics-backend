<?php

namespace App\Modules\Order\Models;

use App\Modules\CompanyCatalog\Models\CompanyCatalogItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPackagingItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'company_catalog_item_id',
        'item_name',
        'item_sku',
        'category',
        'size_label',
        'unit_price',
        'quantity',
        'total_price',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'total_price' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function companyCatalogItem(): BelongsTo
    {
        return $this->belongsTo(CompanyCatalogItem::class);
    }
}
