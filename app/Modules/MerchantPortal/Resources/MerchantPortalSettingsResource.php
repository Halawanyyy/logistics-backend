<?php

namespace App\Modules\MerchantPortal\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'merchant_id' => $this->merchant_id,
            'default_fulfillment_type' => $this->default_fulfillment_type,
            'default_payment_type' => $this->default_payment_type,
            'default_pickup_address' => $this->default_pickup_address,
            'default_label_format' => $this->default_label_format,
            'notification_preferences' => $this->notification_preferences ?? [],
            'integration_preferences' => $this->integration_preferences ?? [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
