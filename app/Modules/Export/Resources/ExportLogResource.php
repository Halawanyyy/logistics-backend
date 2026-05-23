<?php

namespace App\Modules\Export\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExportLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'export_type' => $this->export_type,
            'format' => $this->format,
            'filters' => $this->filters,
            'status' => $this->status,
            'file_path' => $this->file_path,
            'download_url' => route('exports.download', $this->resource),
            'generated_by_name' => $this->whenLoaded('generatedBy', fn () => $this->generatedBy?->name),
            'generated_at' => $this->generated_at,
            'created_at' => $this->created_at,
        ];
    }
}
