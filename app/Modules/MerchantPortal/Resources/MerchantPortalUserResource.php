<?php

namespace App\Modules\MerchantPortal\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);

        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'merchant_name' => $this->merchant?->name,
            'user_id' => $this->user_id,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ],
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'role' => $this->role,
            'role_label' => $labels->label('merchant_portal.role', $this->role),
            'status' => $this->status,
            'status_label' => $labels->label('merchant_portal.status', $this->status),
            'is_primary' => (bool) $this->is_primary,
            'invited_by' => $this->invited_by,
            'invited_by_name' => $this->invitedBy?->name,
            'invited_at' => $this->invited_at,
            'accepted_at' => $this->accepted_at,
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
        ];
    }
}
