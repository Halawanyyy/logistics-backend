<?php

namespace App\Modules\Attachment\Services;

use App\Modules\Attachment\Models\Attachment;
use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, UploadedFile $file, ?int $uploadedBy = null): Attachment
    {
        $attachable = $this->resolveAttachable(
            (string) $payload['attachable_type'],
            (int) $payload['attachable_id'],
        );

        $fileName = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $directory = (string) $payload['attachable_type'];
        $diskPath = $directory.'/'.$fileName;

        Storage::disk('attachments')->putFileAs($directory, $file, $fileName);

        $attachment = Attachment::query()->create([
            'attachable_type' => (string) $payload['attachable_type'],
            'attachable_id' => (int) $payload['attachable_id'],
            'file_name' => $fileName,
            'original_name' => $file->getClientOriginalName(),
            'file_path' => 'attachments/'.$diskPath,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'category' => $payload['category'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'uploaded_by' => $uploadedBy,
        ]);

        $this->auditLogService->log(
            AuditEventType::ATTACHMENT_UPLOADED,
            'uploaded',
            'attachment',
            $attachment->id,
            [
                'user_id' => $uploadedBy,
                'metadata' => [
                    'attachable_type' => $attachment->attachable_type,
                    'attachable_id' => $attachment->attachable_id,
                    'category' => $attachment->category,
                    'original_name' => $attachment->original_name,
                ],
                'notes' => 'Attachment uploaded.',
            ],
        );

        $this->auditLogService->log(
            AuditEventType::ATTACHMENT_UPLOADED,
            'uploaded',
            $attachable,
            null,
            [
                'user_id' => $uploadedBy,
                'metadata' => [
                    'attachment_id' => $attachment->id,
                    'category' => $attachment->category,
                    'original_name' => $attachment->original_name,
                ],
                'notes' => 'Attachment linked to entity.',
            ],
        );

        return $attachment->load('uploadedBy');
    }

    public function delete(Attachment $attachment, ?int $deletedBy = null): void
    {
        $diskPath = $this->diskPath($attachment->file_path);

        if (Storage::disk('attachments')->exists($diskPath)) {
            Storage::disk('attachments')->delete($diskPath);
        }

        $this->auditLogService->log(
            AuditEventType::ATTACHMENT_DELETED,
            'deleted',
            'attachment',
            $attachment->id,
            [
                'user_id' => $deletedBy,
                'metadata' => [
                    'attachable_type' => $attachment->attachable_type,
                    'attachable_id' => $attachment->attachable_id,
                    'original_name' => $attachment->original_name,
                ],
                'notes' => 'Attachment deleted.',
            ],
        );

        $attachment->delete();
    }

    public function diskPath(string $filePath): string
    {
        return str_starts_with($filePath, 'attachments/')
            ? Str::after($filePath, 'attachments/')
            : $filePath;
    }

    private function resolveAttachable(string $type, int $id): Model
    {
        $class = config('attachments.attachable_types.'.$type);

        if (! is_string($class) || ! class_exists($class)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Unsupported attachable type.',
            ], 422));
        }

        /** @var class-string<Model> $class */
        $attachable = $class::query()->find($id);

        if (! $attachable instanceof Model) {
            throw new HttpResponseException(response()->json([
                'message' => 'Attachable entity not found.',
            ], 422));
        }

        return $attachable;
    }
}
