<?php

namespace App\Models;

use App\Modules\Branding\Models\BrandingSetting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'storage_namespace',
    ];

    /**
     * The storage namespace must remain immutable once created.
     */
    public function setStorageNamespaceAttribute(?string $value): void
    {
        if ($this->exists && ! empty($this->attributes['storage_namespace'])) {
            // Immutable: ignore attempts to mutate an existing storage namespace
            return;
        }

        $this->attributes['storage_namespace'] = $value;
    }

    public function brandingSetting(): HasOne
    {
        return $this->hasOne(BrandingSetting::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
