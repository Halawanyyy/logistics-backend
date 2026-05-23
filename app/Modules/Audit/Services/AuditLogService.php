<?php

namespace App\Modules\Audit\Services;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Settings\Services\SystemSettingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use BackedEnum;
use UnitEnum;

class AuditLogService
{
    private static ?bool $auditLogTableExists = null;

    public function __construct(
        private readonly SystemSettingService $systemSettingService,
    ) {
    }

    /**
     * @param  Model|string  $auditable
     * @param  array<string, mixed>  $context
     */
    public function log(
        string $eventType,
        string $action,
        Model|string $auditable,
        ?int $auditableId = null,
        array $context = [],
    ): ?AuditLog {
        if (! $this->canWriteLogs()) {
            return null;
        }

        [$auditableType, $resolvedAuditableId] = $this->resolveAuditable($auditable, $auditableId);
        [$userId, $userName] = $this->resolveActor($context);

        $metadata = $this->serializeValue($context['metadata'] ?? null);
        $request = $this->resolveRequest();

        if ($request !== null) {
            $metadata = array_filter([
                ...($metadata ?? []),
                'request_method' => $request->method(),
                'request_path' => $request->path(),
            ], static fn ($value): bool => $value !== null);
        }

        return AuditLog::query()->create([
            'user_id' => $userId,
            'user_name' => $userName,
            'event_type' => $eventType,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $resolvedAuditableId,
            'old_values' => $this->serializeValue($context['old_values'] ?? null),
            'new_values' => $this->serializeValue($context['new_values'] ?? null),
            'metadata' => $metadata,
            'ip_address' => $context['ip_address'] ?? $request?->ip(),
            'user_agent' => $context['user_agent'] ?? $request?->userAgent(),
            'notes' => $context['notes'] ?? null,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $metadata
     */
    public function logModelCreated(
        Model $model,
        ?string $eventType = null,
        ?string $notes = null,
        array $metadata = [],
    ): ?AuditLog {
        return $this->log(
            $eventType ?? $this->defaultEventType($model, 'created'),
            'created',
            $model,
            null,
            [
                'new_values' => $this->extractAuditValues($model),
                'metadata' => $metadata,
                'notes' => $notes,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $metadata
     */
    public function logModelUpdated(
        Model $model,
        array $oldValues,
        array $newValues,
        ?string $eventType = null,
        ?string $notes = null,
        array $metadata = [],
    ): ?AuditLog {
        if ($oldValues === [] && $newValues === []) {
            return null;
        }

        return $this->log(
            $eventType ?? $this->defaultEventType($model, 'updated'),
            'updated',
            $model,
            null,
            [
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'metadata' => $metadata,
                'notes' => $notes,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $metadata
     */
    public function logModelDeleted(
        Model $model,
        array $oldValues = [],
        ?string $eventType = null,
        ?string $notes = null,
        array $metadata = [],
    ): ?AuditLog {
        return $this->log(
            $eventType ?? $this->defaultEventType($model, 'deleted'),
            'deleted',
            $model,
            null,
            [
                'old_values' => $oldValues === [] ? $this->extractAuditValues($model) : $oldValues,
                'metadata' => $metadata,
                'notes' => $notes,
            ],
        );
    }

    /**
     * @param  Model|string  $auditable
     * @param  array<string, mixed>  $metadata
     */
    public function logStatusChange(
        Model|string $auditable,
        string $eventType,
        mixed $oldStatus,
        mixed $newStatus,
        array $metadata = [],
        ?string $notes = null,
        ?int $auditableId = null,
        ?string $action = null,
    ): ?AuditLog {
        return $this->log(
            $eventType,
            $action ?? 'status_changed',
            $auditable,
            $auditableId,
            [
                'old_values' => ['status' => $this->serializeValue($oldStatus)],
                'new_values' => ['status' => $this->serializeValue($newStatus)],
                'metadata' => $metadata,
                'notes' => $notes,
            ],
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, AuditLog>
     */
    public function getEntityTimeline(string $auditableType, int $auditableId)
    {
        return AuditLog::query()
            ->where('auditable_type', $this->normalizeAuditableType($auditableType))
            ->where('auditable_id', $auditableId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAuditLogs(array $filters = []): LengthAwarePaginator
    {
        $defaultPerPage = $this->systemSettingService->defaultPaginationLimit(50);
        $perPage = min(max((int) ($filters['per_page'] ?? $defaultPerPage), 1), 200);

        return AuditLog::query()
            ->when(isset($filters['user_id']), fn (Builder $query) => $query->where('user_id', $filters['user_id']))
            ->when(isset($filters['event_type']), fn (Builder $query) => $query->where('event_type', $filters['event_type']))
            ->when(isset($filters['action']), fn (Builder $query) => $query->where('action', $filters['action']))
            ->when(isset($filters['auditable_type']), fn (Builder $query) => $query->where('auditable_type', $this->normalizeAuditableType((string) $filters['auditable_type'])))
            ->when(isset($filters['auditable_id']), fn (Builder $query) => $query->where('auditable_id', $filters['auditable_id']))
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $search = (string) $filters['search'];

                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('user_name', 'like', '%'.$search.'%')
                        ->orWhere('notes', 'like', '%'.$search.'%')
                        ->orWhere('event_type', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function filterValues(array $values): array
    {
        $excluded = array_map('strtolower', array_merge(
            config('audit.excluded_fields', []),
            config('audit.excluded_auto_fields', [])
        ));

        $filtered = [];

        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), $excluded, true)) {
                continue;
            }

            $filtered[$key] = $this->serializeValue($value);
        }

        return $filtered;
    }

    /**
     * @return array<string, mixed>
     */
    public function extractAuditValues(Model $model, ?array $only = null): array
    {
        $attributes = $only === null
            ? $model->getAttributes()
            : Arr::only($model->getAttributes(), $only);

        return $this->filterValues($attributes);
    }

    private function canWriteLogs(): bool
    {
        if (self::$auditLogTableExists !== null) {
            return self::$auditLogTableExists;
        }

        return self::$auditLogTableExists = Schema::hasTable('audit_logs');
    }

    /**
     * @param  Model|string  $auditable
     * @return array{0: string, 1: int}
     */
    private function resolveAuditable(Model|string $auditable, ?int $auditableId = null): array
    {
        if ($auditable instanceof Model) {
            return [$this->normalizeAuditableType($auditable::class), (int) $auditable->getKey()];
        }

        return [$this->normalizeAuditableType($auditable), (int) $auditableId];
    }

    private function normalizeAuditableType(string $auditableType): string
    {
        $aliases = config('audit.aliases', []);

        if (isset($aliases[$auditableType])) {
            return $auditableType;
        }

        $alias = array_search($auditableType, $aliases, true);

        if ($alias !== false) {
            return $alias;
        }

        return (string) str($auditableType)->classBasename()->snake();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: ?int, 1: ?string}
     */
    private function resolveActor(array $context): array
    {
        $user = $context['user'] ?? auth()->user();

        if ($user instanceof User) {
            return [$user->id, $user->name];
        }

        if (isset($context['user_id'])) {
            $userName = $context['user_name'] ?? User::query()->whereKey($context['user_id'])->value('name');

            return [(int) $context['user_id'], $userName];
        }

        if (isset($context['user_name'])) {
            return [null, (string) $context['user_name']];
        }

        return [null, null];
    }

    private function resolveRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        return $request instanceof Request ? $request : null;
    }

    private function defaultEventType(Model $model, string $action): string
    {
        return $this->normalizeAuditableType($model::class).'_'.$action;
    }

    private function serializeValue(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return [
                'id' => $value->getKey(),
                'type' => $this->normalizeAuditableType($value::class),
            ];
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof Carbon) {
            return $value->toISOString();
        }

        if (is_array($value)) {
            $serialized = [];

            foreach ($value as $key => $nestedValue) {
                if (is_string($key) && in_array(strtolower($key), array_map('strtolower', config('audit.excluded_fields', [])), true)) {
                    continue;
                }

                $serialized[$key] = $this->serializeValue($nestedValue);
            }

            return $serialized;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return $value;
    }
}
