<?php

namespace App\Modules\Attachment\Controllers;

use App\Modules\Attachment\Models\Attachment;
use App\Modules\Attachment\Resources\AttachmentResource;
use App\Modules\Attachment\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AttachmentController
{
    public function __construct(
        private readonly AttachmentService $attachmentService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'attachable_type' => ['nullable', 'string', Rule::in(array_keys(config('attachments.attachable_types', [])))],
            'attachable_id' => ['nullable', 'integer', 'min:1'],
            'category' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $attachments = Attachment::query()
            ->with('uploadedBy')
            ->when(isset($validated['attachable_type']), fn ($query) => $query->where('attachable_type', $validated['attachable_type']))
            ->when(isset($validated['attachable_id']), fn ($query) => $query->where('attachable_id', $validated['attachable_id']))
            ->when(isset($validated['category']), fn ($query) => $query->where('category', $validated['category']))
            ->latest()
            ->paginate((int) ($validated['per_page'] ?? 50));

        return AttachmentResource::collection($attachments);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attachable_type' => ['required', 'string', Rule::in(array_keys(config('attachments.attachable_types', [])))],
            'attachable_id' => ['required', 'integer', 'min:1'],
            'category' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,csv,xlsx'],
            'notes' => ['nullable', 'string'],
        ]);

        $attachment = $this->attachmentService->create(
            $validated,
            $request->file('file'),
            $request->user()?->id,
        );

        return response()->json([
            'data' => (new AttachmentResource($attachment))->resolve(),
        ], 201);
    }

    public function show(Attachment $attachment): AttachmentResource
    {
        return new AttachmentResource($attachment->load('uploadedBy'));
    }

    public function download(Attachment $attachment)
    {
        $diskPath = $this->attachmentService->diskPath($attachment->file_path);
        abort_unless(Storage::disk('attachments')->exists($diskPath), 404, 'Attachment file not found.');

        return Response::download(
            Storage::disk('attachments')->path($diskPath),
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?? 'application/octet-stream']
        );
    }

    public function destroy(Request $request, Attachment $attachment): JsonResponse
    {
        $this->attachmentService->delete($attachment, $request->user()?->id);

        return response()->json([
            'message' => 'Attachment deleted successfully',
        ]);
    }
}
