<?php

namespace App\Modules\MerchantPortal\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalMeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);

        return [
            'user' => [
                'id' => $this->resource['user']->id,
                'name' => $this->resource['user']->name,
                'email' => $this->resource['user']->email,
            ],
            'merchant' => [
                'id' => $this->resource['merchant']->id,
                'name' => $this->resource['merchant']->name,
                'company_name' => $this->resource['merchant']->company_name,
                'phone' => $this->resource['merchant']->phone,
                'email' => $this->resource['merchant']->email,
                'address' => $this->resource['merchant']->address,
                'status' => $this->resource['merchant']->status,
            ],
            'portal_role' => $this->resource['merchant_user']->role,
            'portal_role_label' => $labels->label('merchant_portal.role', $this->resource['merchant_user']->role),
            'portal_status' => $this->resource['merchant_user']->status,
            'portal_status_label' => $labels->label('merchant_portal.status', $this->resource['merchant_user']->status),
            'capabilities' => $this->resource['capabilities'],
        ];
    }
}
