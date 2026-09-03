<?php

namespace App\Modules\Waybill\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaybillBatch extends Model
{
    protected $fillable = [
        'batch_number',
        'quantity',
        'first_waybill_number',
        'last_waybill_number',
        'label_template',
        'generated_by',
        'generated_at',
        'notes',
    ];

    protected function casts(): array
    {
        return ['generated_at' => 'datetime'];
    }

    public function waybills(): HasMany
    {
        return $this->hasMany(Waybill::class, 'batch_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
