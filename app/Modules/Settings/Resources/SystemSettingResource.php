<?php

namespace App\Modules\Settings\Resources;

use App\Modules\Settings\Services\SystemSettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settingsService = app(SystemSettingService::class);

        return [
            'id' => $this->id,
            'group' => $this->group,
            'key' => $this->key,
            'value' => $settingsService->castValue($this->resource, true),
            'type' => $this->type,
            'label' => $this->label,
            'description' => $this->description,
            'is_public' => $this->is_public,
            'is_encrypted' => $this->is_encrypted,
            'is_editable' => $this->is_editable,
            'options' => $this->options,
            'validation_rules' => $this->validation_rules,
            'sort_order' => $this->sort_order,
            'updated_by_name' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'updated_at' => $this->updated_at,
        ];
    }
}
