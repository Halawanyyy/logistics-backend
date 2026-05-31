<?php

namespace App\Modules\MerchantPortal\Models;

use App\Modules\Warehouse\Models\WarehouseProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantInboundStockRequestItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'inbound_request_id',
        'warehouse_product_id',
        'requested_product_name',
        'requested_sku',
        'quantity',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function inboundRequest(): BelongsTo
    {
        return $this->belongsTo(MerchantInboundStockRequest::class, 'inbound_request_id');
    }

    public function warehouseProduct(): BelongsTo
    {
        return $this->belongsTo(WarehouseProduct::class);
    }
}
