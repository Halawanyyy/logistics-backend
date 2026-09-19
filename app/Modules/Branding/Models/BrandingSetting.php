<?php

namespace App\Modules\Branding\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BrandingSetting extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'logo_path',
        'logo_disk',
        'app_name',
        'legacy_logo_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Resolves the active browser-usable logo URL with fallback to legacy external URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (! empty($this->logo_path)) {
            $disk = $this->logo_disk ?: 'public';

            if ($disk === 'public') {
                if (request()->hasHeader('host')) {
                    $storagePath = '/storage/'.ltrim($this->logo_path, '/');
                    return url($storagePath);
                }
            }

            return Storage::disk($disk)->url($this->logo_path);
        }

        if (! empty($this->legacy_logo_url)) {
            return $this->legacy_logo_url;
        }

        return null;
    }
}
