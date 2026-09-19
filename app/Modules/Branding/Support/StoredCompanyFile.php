<?php

namespace App\Modules\Branding\Support;

class StoredCompanyFile
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $disk,
        public readonly string $path,
        public readonly string $url,
        public readonly string $originalName,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly string $storageNamespace,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'disk' => $this->disk,
            'path' => $this->path,
            'url' => $this->url,
            'original_name' => $this->originalName,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'storage_namespace' => $this->storageNamespace,
        ];
    }
}
