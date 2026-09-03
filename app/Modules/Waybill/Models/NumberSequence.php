<?php

namespace App\Modules\Waybill\Models;

use Illuminate\Database\Eloquent\Model;

class NumberSequence extends Model
{
    protected $fillable = ['key', 'current_value', 'prefix', 'padding'];

    protected function casts(): array
    {
        return [
            'current_value' => 'integer',
            'padding' => 'integer',
        ];
    }
}
