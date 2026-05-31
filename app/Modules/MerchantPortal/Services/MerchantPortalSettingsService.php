<?php

namespace App\Modules\MerchantPortal\Services;

use App\Models\Merchant;
use App\Modules\MerchantPortal\Models\MerchantPortalSetting;

class MerchantPortalSettingsService
{
    public function getForMerchant(Merchant $merchant): MerchantPortalSetting
    {
        return MerchantPortalSetting::query()->firstOrCreate(
            ['merchant_id' => $merchant->id],
            [
                'default_fulfillment_type' => null,
                'default_payment_type' => null,
                'default_pickup_address' => $merchant->address,
                'default_label_format' => 'html',
                'notification_preferences' => [],
                'integration_preferences' => [],
            ],
        );
    }

    /**
     * @param array<string, mixed> $validated
     */
    public function updateForMerchant(Merchant $merchant, array $validated): MerchantPortalSetting
    {
        $settings = $this->getForMerchant($merchant);
        $settings->update($validated);

        return $settings->fresh();
    }
}
