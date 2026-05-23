<?php

namespace App\Modules\Export\Controllers;

use App\Modules\Export\Models\ExportLog;
use App\Modules\Export\Resources\ExportLogResource;
use App\Modules\Export\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class ExportController
{
    public function __construct(
        private readonly ExportService $exportService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'export_type' => ['nullable', 'string', 'max:255'],
            'format' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $exports = ExportLog::query()
            ->with('generatedBy')
            ->when(isset($validated['export_type']), fn ($query) => $query->where('export_type', $validated['export_type']))
            ->when(isset($validated['format']), fn ($query) => $query->where('format', $validated['format']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['date_from']), fn ($query) => $query->whereDate('generated_at', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn ($query) => $query->whereDate('generated_at', '<=', $validated['date_to']))
            ->latest('generated_at')
            ->paginate((int) ($validated['per_page'] ?? 50));

        return ExportLogResource::collection($exports);
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $validated = $request->validate([
            'format' => ['nullable', 'string', 'max:50'],
            'filters' => ['nullable', 'array'],
        ]);

        $export = $this->exportService->generate(
            $type,
            (string) ($validated['format'] ?? 'csv'),
            $validated['filters'] ?? [],
            $request->user()?->id,
        );

        return response()->json([
            'data' => (new ExportLogResource($export))->resolve(),
        ], 201);
    }

    public function show(ExportLog $exportLog): ExportLogResource
    {
        return new ExportLogResource($exportLog->load('generatedBy'));
    }

    public function download(ExportLog $exportLog)
    {
        abort_unless($exportLog->file_path !== null, 404, 'Export file not found.');

        $diskPath = $this->exportService->diskPath($exportLog->file_path);
        abort_unless(Storage::disk('exports')->exists($diskPath), 404, 'Export file not found.');

        return Response::download(
            Storage::disk('exports')->path($diskPath),
            basename($diskPath),
            ['Content-Type' => 'text/csv']
        );
    }
}
