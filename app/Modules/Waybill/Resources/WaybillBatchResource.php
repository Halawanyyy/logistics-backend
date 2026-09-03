<?php

namespace App\Modules\Waybill\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaybillBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_number' => $this->batch_number,
            'quantity' => $this->quantity,
            'first_waybill_number' => $this->first_waybill_number,
            'last_waybill_number' => $this->last_waybill_number,
            'label_template' => $this->label_template,
            'available_count' => (int) ($this->available_count ?? $this->waybills->where('status', 'available')->count()),
            'assigned_count' => (int) ($this->assigned_count ?? $this->waybills->where('status', 'assigned')->count()),
            'void_count' => (int) ($this->void_count ?? $this->waybills->where('status', 'void')->count()),
            'generated_at' => $this->generated_at,
            'generated_by' => $this->generatedBy === null ? null : [
                'id' => $this->generatedBy->id,
                'name' => $this->generatedBy->name,
            ],
            'notes' => $this->notes,
            'waybills' => $this->whenLoaded('waybills', fn () => WaybillResource::collection($this->waybills)),
        ];
    }
}
