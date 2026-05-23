<?php

namespace App\Modules\Driver\Models;

use App\Modules\Shipment\Models\Shipment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverManifestItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'driver_manifest_id',
        'shipment_id',
        'shipment_number',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'cod_amount',
        'shipping_fee',
        'shipment_status_at_loadout',
        'final_status',
        'delivered_cod_amount',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cod_amount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'delivered_cod_amount' => 'decimal:2',
        ];
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(DriverManifest::class, 'driver_manifest_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
