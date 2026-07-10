<?php

namespace App\Modules\CompanyCatalog\Services;

use App\Models\Merchant;
use App\Models\User;
use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\CompanyCatalog\Models\CompanyCatalogItem;
use App\Modules\CompanyCatalog\Resources\CompanyCatalogItemResource;
use App\Modules\Settings\Services\SystemSettingService;
use App\Modules\Warehouse\Models\PickPackFeeRule;
use App\Modules\Warehouse\Models\StorageFeeRule;
use App\Modules\Warehouse\Resources\PickPackFeeRuleResource;
use App\Modules\Warehouse\Resources\StorageFeeRuleResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;

class MerchantShopService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly SystemSettingService $systemSettingService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function catalog(array $filters = []): array
    {
        $this->ensureEnabled();

        return CompanyCatalogItemResource::collection(
            $this->catalogQuery($filters)->get()
        )->resolve();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function pricing(?Merchant $merchant = null, array $filters = [], ?User $viewer = null, bool $logPreview = false): array
    {
        $this->ensureEnabled();

        $packagingProducts = $this->systemSettingService->get('merchant_shop_show_packaging_products', true)
            ? $this->merchantVisibleCatalogQuery()
                ->where('type', CompanyCatalogItem::TYPE_PACKAGING_PRODUCT)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
            : collect();

        $services = $this->merchantVisibleCatalogQuery()
            ->where('type', '!=', CompanyCatalogItem::TYPE_PACKAGING_PRODUCT)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $data = [
            'packaging_products' => CompanyCatalogItemResource::collection($packagingProducts)->resolve(),
            'storage_fees' => $this->systemSettingService->get('merchant_shop_show_storage_fees', true)
                ? StorageFeeRuleResource::collection($this->storageFeeRulesForMerchant($merchant)->get())->resolve()
                : [],
            'pick_pack_fees' => $this->systemSettingService->get('merchant_shop_show_pick_pack_fees', true)
                ? PickPackFeeRuleResource::collection($this->pickPackFeeRulesForMerchant($merchant)->get())->resolve()
                : [],
            'services' => CompanyCatalogItemResource::collection($services)->resolve(),
        ];

        if ($logPreview && $merchant !== null) {
            $this->auditLogService->log(
                AuditEventType::MERCHANT_SHOP_PREVIEWED,
                'previewed',
                $merchant,
                null,
                [
                    'user' => $viewer,
                    'metadata' => [
                        'merchant_id' => $merchant->id,
                        'filters' => $filters,
                    ],
                    'notes' => 'Merchant shop preview viewed.',
                ],
            );
        }

        return ['data' => $data];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<CompanyCatalogItem>
     */
    public function catalogQuery(array $filters = []): Builder
    {
        return $this->merchantVisibleCatalogQuery()
            ->whereIn('type', [
                CompanyCatalogItem::TYPE_PACKAGING_PRODUCT,
                CompanyCatalogItem::TYPE_FULFILLMENT_SERVICE,
                CompanyCatalogItem::TYPE_OTHER_SERVICE,
            ])
            ->when(isset($filters['type']), fn (Builder $query) => $query->where('type', $filters['type']))
            ->when(isset($filters['category']), fn (Builder $query) => $query->where('category', $filters['category']))
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->when(
                ! $this->systemSettingService->get('merchant_shop_show_packaging_products', true),
                fn (Builder $query) => $query->where('type', '!=', CompanyCatalogItem::TYPE_PACKAGING_PRODUCT)
            )
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * @return Builder<StorageFeeRule>
     */
    public function storageFeeRulesForMerchant(?Merchant $merchant): Builder
    {
        return StorageFeeRule::query()
            ->with(['merchant', 'pricingPlan'])
            ->where('status', 'active')
            ->when($merchant !== null, function (Builder $query) use ($merchant): void {
                $query->where(function (Builder $inner) use ($merchant): void {
                    $inner->where('merchant_id', $merchant->id)
                        ->orWhere(function (Builder $fallback) use ($merchant): void {
                            $fallback
                                ->whereNull('merchant_id')
                                ->where('pricing_plan_id', $merchant->pricing_plan_id);
                        });
                });
            })
            ->orderByDesc('merchant_id')
            ->orderBy('name');
    }

    /**
     * @return Builder<PickPackFeeRule>
     */
    public function pickPackFeeRulesForMerchant(?Merchant $merchant): Builder
    {
        return PickPackFeeRule::query()
            ->with(['merchant', 'pricingPlan'])
            ->where('status', 'active')
            ->when($merchant !== null, function (Builder $query) use ($merchant): void {
                $query->where(function (Builder $inner) use ($merchant): void {
                    $inner->where('merchant_id', $merchant->id)
                        ->orWhere(function (Builder $fallback) use ($merchant): void {
                            $fallback
                                ->whereNull('merchant_id')
                                ->where('pricing_plan_id', $merchant->pricing_plan_id);
                        });
                });
            })
            ->orderByDesc('merchant_id')
            ->orderBy('name');
    }

    private function merchantVisibleCatalogQuery(): Builder
    {
        return CompanyCatalogItem::query()
            ->with(['createdBy', 'updatedBy'])
            ->merchantShopVisible();
    }

    private function ensureEnabled(): void
    {
        if (! $this->systemSettingService->get('merchant_shop_enabled', true)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Merchant shop is currently disabled.',
            ], 404));
        }
    }
}
