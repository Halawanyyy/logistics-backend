<?php

namespace App\Modules\MerchantPortal\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'context' => $this->context ?? null,
            'resource_id' => $this->attachable_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'category' => $this->category,
            'notes' => $this->notes,
            'merchant_uploaded' => (bool) $this->merchant_uploaded,
            'download_url' => '/api/merchant-portal/attachments/'.$this->id.'/download',
            'created_at' => $this->created_at,
        ];
    }
}
