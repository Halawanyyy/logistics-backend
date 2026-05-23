<?php

namespace App\Traits;

use App\Modules\Audit\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait Auditable
{
    /**
     * @var array<string, mixed>
     */
    protected array $auditOldValues = [];

    /**
     * @var array<string, mixed>
     */
    protected array $auditNewValues = [];

    protected bool $auditLoggingDisabled = false;

    public static function bootAuditable(): void
    {
        static::updating(function (Model $model): void {
            if (! $model->shouldAutoLogAudit()) {
                return;
            }

            $dirtyKeys = array_values(array_diff(
                array_keys($model->getDirty()),
                $model->auditExcludedFields()
            ));

            /** @var self $model */
            $auditLogService = app(AuditLogService::class);
            $model->auditOldValues = $auditLogService->filterValues(Arr::only($model->getOriginal(), $dirtyKeys));
            $model->auditNewValues = $auditLogService->filterValues(Arr::only($model->getDirty(), $dirtyKeys));
        });

        static::deleting(function (Model $model): void {
            if (! $model->shouldAutoLogAudit()) {
                return;
            }

            /** @var self $model */
            $model->auditOldValues = app(AuditLogService::class)->extractAuditValues($model);
        });

        static::created(function (Model $model): void {
            /** @var self $model */
            if (! $model->shouldAutoLogAudit()) {
                return;
            }

            app(AuditLogService::class)->logModelCreated(
                $model,
                $model->auditEventType('created'),
            );
        });

        static::updated(function (Model $model): void {
            /** @var self $model */
            if (! $model->shouldAutoLogAudit()) {
                return;
            }

            app(AuditLogService::class)->logModelUpdated(
                $model,
                $model->auditOldValues,
                $model->auditNewValues,
                $model->auditEventType('updated'),
            );
        });

        static::deleted(function (Model $model): void {
            /** @var self $model */
            if (! $model->shouldAutoLogAudit()) {
                return;
            }

            app(AuditLogService::class)->logModelDeleted(
                $model,
                $model->auditOldValues,
                $model->auditEventType('deleted'),
            );
        });
    }

    public function disableAuditLogging(): static
    {
        $this->auditLoggingDisabled = true;

        return $this;
    }

    public function enableAuditLogging(): static
    {
        $this->auditLoggingDisabled = false;

        return $this;
    }

    protected function shouldAutoLogAudit(): bool
    {
        return ! $this->auditLoggingDisabled
            && ($this->auditAutoLogsEnabled ?? true) === true;
    }

    /**
     * @return list<string>
     */
    protected function auditExcludedFields(): array
    {
        return array_values(array_unique(array_merge(
            config('audit.excluded_fields', []),
            config('audit.excluded_auto_fields', []),
            $this->auditExcluded ?? []
        )));
    }

    protected function auditEventType(string $action): ?string
    {
        $configured = $this->auditEventTypes[$action] ?? null;

        if ($configured !== null) {
            return $configured;
        }

        return (string) str(class_basename($this))->snake().'_'.$action;
    }
}
