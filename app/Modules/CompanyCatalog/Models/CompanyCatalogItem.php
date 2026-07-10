<?php

namespace App\Modules\CompanyCatalog\Models;

use App\Models\User;
use App\Modules\Order\Models\OrderPackagingItem;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyCatalogItem extends Model
{
    use Auditable, SoftDeletes;

    public const TYPE_PACKAGING_PRODUCT = 'packaging_product';

    public const TYPE_STORAGE_SERVICE = 'storage_service';

    public const TYPE_FULFILLMENT_SERVICE = 'fulfillment_service';

    public const TYPE_OTHER_SERVICE = 'other_service';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /**
     * @var array<string, string>
     */
    protected $auditEventTypes = [
        'created' => 'company_catalog_item_created',
        'updated' => 'company_catalog_item_updated',
        'deleted' => 'company_catalog_item_deleted',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'category',
        'name',
        'sku',
        'description',
        'unit_price',
        'currency',
        'size_label',
        'length',
        'width',
        'height',
        'weight',
        'unit',
        'image_path',
        'is_visible_to_merchants',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'weight' => 'decimal:2',
            'is_visible_to_merchants' => 'boolean',
            'sort_order' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_PACKAGING_PRODUCT,
            self::TYPE_STORAGE_SERVICE,
            self::TYPE_FULFILLMENT_SERVICE,
            self::TYPE_OTHER_SERVICE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function orderPackagingItems(): HasMany
    {
        return $this->hasMany(OrderPackagingItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeVisibleToMerchants($query)
    {
        return $query->where('is_visible_to_merchants', true);
    }

    public function scopeMerchantShopVisible($query)
    {
        return $query->active()->visibleToMerchants();
    }

    public function scopePackagingProducts($query)
    {
        return $query->where('type', self::TYPE_PACKAGING_PRODUCT);
    }
}
