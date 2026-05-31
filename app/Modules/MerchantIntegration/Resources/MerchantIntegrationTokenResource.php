<?php

namespace App\Modules\MerchantIntegration\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantIntegrationTokenResource extends JsonResource
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
            'merchant_name' => $this->whenLoaded('merchant', fn () => $this->merchant?->name),
            'name' => $this->name,
            'provider' => $this->provider,
            'token_prefix' => $this->token_prefix,
            'permissions' => $this->permissions ?? [],
            'status' => $this->status,
            'status_label' => $labels->label('integrations.token_status', $this->status),
            'last_used_at' => $this->last_used_at,
            'expires_at' => $this->expires_at,
            'revoked_at' => $this->revoked_at,
            'revoked_by_name' => $this->whenLoaded('revokedBy', fn () => $this->revokedBy?->name),
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
