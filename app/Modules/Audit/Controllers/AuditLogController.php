<?php

namespace App\Modules\Audit\Controllers;

use App\Modules\Audit\Resources\AuditLogResource;
use App\Modules\Audit\Services\AuditLogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'event_type' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:255'],
            'auditable_type' => ['nullable', 'string', 'max:255'],
            'auditable_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return AuditLogResource::collection(
            $this->auditLogService->getAuditLogs($validated)
        );
    }

    public function timeline(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auditable_type' => ['required', 'string', 'max:255'],
            'auditable_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json([
            'data' => AuditLogResource::collection(
                $this->auditLogService->getEntityTimeline(
                    $validated['auditable_type'],
                    (int) $validated['auditable_id'],
                )
            )->resolve(),
        ]);
    }
}
