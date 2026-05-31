<?php

namespace App\Modules\MerchantPortal\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->export_type,
            'format' => $this->format,
            'filters' => $this->filters,
            'status' => $this->status,
            'generated_at' => $this->generated_at,
            'created_at' => $this->created_at,
            'can_download' => $this->file_path !== null,
            'download_url' => $this->file_path !== null
                ? '/api/merchant-portal/exports/'.$this->id.'/download'
                : null,
        ];
    }
}
