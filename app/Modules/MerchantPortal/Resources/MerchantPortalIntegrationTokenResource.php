<?php

namespace App\Modules\MerchantPortal\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalIntegrationTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'provider' => $this->provider,
            'token_prefix' => $this->token_prefix,
            'abilities' => $this->permissions ?? [],
            'status' => $this->status,
            'status_label' => $labels->label('integrations.token_status', $this->status),
            'last_used_at' => $this->last_used_at,
            'expires_at' => $this->expires_at,
            'revoked_at' => $this->revoked_at,
            'created_at' => $this->created_at,
        ];
    }
}
