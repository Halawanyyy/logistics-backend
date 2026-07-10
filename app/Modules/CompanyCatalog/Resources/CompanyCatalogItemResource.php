<?php

namespace App\Modules\CompanyCatalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyCatalogItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'category' => $this->category,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'unit_price' => $this->unit_price,
            'currency' => $this->currency,
            'size_label' => $this->size_label,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'weight' => $this->weight,
            'unit' => $this->unit,
            'image_path' => $this->image_path,
            'is_visible_to_merchants' => (bool) $this->is_visible_to_merchants,
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'updated_by_name' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'created_at' => $this->created_at,
        ];
    }
}
