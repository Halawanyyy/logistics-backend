<?php

namespace App\Modules\Settings\Controllers;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Settings\Resources\SystemSettingResource;
use App\Modules\Settings\Services\SystemSettingService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SystemSettingController
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly SystemSettingService $systemSettingService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'group' => ['nullable', 'string', 'max:255'],
            'key' => ['nullable', 'string', 'max:255'],
            'is_public' => ['nullable', 'boolean'],
            'is_editable' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? $this->systemSettingService->defaultPaginationLimit());

        $settings = SystemSetting::query()
            ->with('updatedBy')
            ->when(isset($validated['group']), fn ($query) => $query->where('group', $validated['group']))
            ->when(isset($validated['key']), fn ($query) => $query->where('key', $validated['key']))
            ->when(array_key_exists('is_public', $validated), fn ($query) => $query->where('is_public', (bool) $validated['is_public']))
            ->when(array_key_exists('is_editable', $validated), fn ($query) => $query->where('is_editable', (bool) $validated['is_editable']))
            ->when(isset($validated['search']), function ($query) use ($validated) {
                $search = $validated['search'];

                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('group', 'like', '%'.$search.'%')
                        ->orWhere('key', 'like', '%'.$search.'%')
                        ->orWhere('label', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('key')
            ->paginate($perPage);

        return SystemSettingResource::collection($settings);
    }

    public function groups(): JsonResponse
    {
        $groups = SystemSetting::query()
            ->select('group', DB::raw('COUNT(*) as settings_count'))
            ->groupBy('group')
            ->orderBy('group')
            ->get();

        return response()->json([
            'data' => $groups->map(fn ($group) => [
                'group' => $group->group,
                'settings_count' => (int) $group->settings_count,
            ])->values(),
        ]);
    }

    public function group(string $group): AnonymousResourceCollection
    {
        $settings = $this->systemSettingService->getGroup($group);

        return SystemSettingResource::collection($settings);
    }

    public function show(SystemSetting $setting): SystemSettingResource
    {
        return new SystemSettingResource($setting->load('updatedBy'));
    }

    public function update(Request $request, SystemSetting $setting): SystemSettingResource
    {
        $this->ensureEditable($setting);
        $value = $request->input('value');
        $this->validateValue($setting, $value);

        $oldValue = $this->maskedAuditValue($setting);
        $updated = $this->systemSettingService->set($setting->key, $value, $request->user()?->id);

        $this->auditLogService->log(
            AuditEventType::SETTING_UPDATED,
            'updated',
            'system_setting',
            $updated->id,
            [
                'user' => $request->user(),
                'old_values' => ['value' => $oldValue],
                'new_values' => ['value' => $this->maskedAuditValue($updated)],
                'metadata' => ['key' => $updated->key, 'group' => $updated->group],
                'notes' => $updated->is_encrypted ? 'Encrypted setting updated.' : 'Setting updated.',
            ],
        );

        return new SystemSettingResource($updated);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.key' => ['required', 'string', 'max:255'],
            'settings.*.value' => ['nullable'],
        ]);

        $keys = collect($validated['settings'])->pluck('key')->unique()->values();
        $settings = SystemSetting::query()->whereIn('key', $keys)->get()->keyBy('key');

        if ($settings->count() !== $keys->count()) {
            return response()->json([
                'message' => 'One or more setting keys are invalid.',
            ], 422);
        }

        $updatedSettings = DB::transaction(function () use ($validated, $settings, $request) {
            $updated = collect();

            foreach ($validated['settings'] as $payload) {
                /** @var SystemSetting $setting */
                $setting = $settings->get($payload['key']);
                $this->ensureEditable($setting);
                $this->validateValue($setting, $payload['value'] ?? null);
                $updated->push($this->systemSettingService->set($setting->key, $payload['value'] ?? null, $request->user()?->id));
            }

            return $updated;
        });

        $this->auditLogService->log(
            AuditEventType::SETTINGS_BULK_UPDATED,
            'bulk_updated',
            'system_setting',
            0,
            [
                'user' => $request->user(),
                'metadata' => [
                    'keys' => $updatedSettings->pluck('key')->values()->all(),
                    'count' => $updatedSettings->count(),
                ],
                'notes' => 'Bulk settings update completed.',
            ],
        );

        return response()->json([
            'data' => SystemSettingResource::collection($updatedSettings)->resolve(),
        ]);
    }

    public function publicIndex(): JsonResponse
    {
        $settings = $this->systemSettingService->getPublicSettings();

        return response()->json([
            'data' => $settings->map(function (SystemSetting $setting) {
                if ($setting->is_encrypted) {
                    return null;
                }

                return [
                    'group' => $setting->group,
                    'key' => $setting->key,
                    'value' => $this->systemSettingService->castValue($setting),
                    'type' => $setting->type,
                    'label' => $setting->label,
                    'description' => $setting->description,
                    'options' => $setting->options,
                    'sort_order' => $setting->sort_order,
                ];
            })->filter()->values(),
        ]);
    }

    private function ensureEditable(SystemSetting $setting): void
    {
        if (! $setting->is_editable) {
            throw new HttpResponseException(response()->json([
                'message' => 'This setting is not editable.',
            ], 422));
        }
    }

    private function validateValue(SystemSetting $setting, mixed $value): void
    {
        $rules = $this->baseRulesForType($setting->type);
        $configuredRules = $setting->validation_rules['rules'] ?? null;

        if (is_string($configuredRules) && $configuredRules !== '') {
            $rules = array_merge($rules, explode('|', $configuredRules));
        }

        $validator = Validator::make(['value' => $value], [
            'value' => $rules,
        ]);

        $validator->validate();
    }

    /**
     * @return list<string>
     */
    private function baseRulesForType(string $type): array
    {
        return match ($type) {
            'integer' => ['nullable', 'integer'],
            'decimal' => ['nullable', 'numeric'],
            'boolean' => ['nullable', 'boolean'],
            'json' => ['nullable', 'array'],
            'date' => ['nullable', 'date'],
            'time' => ['nullable', 'date_format:H:i:s'],
            default => ['nullable', 'string'],
        };
    }

    private function maskedAuditValue(SystemSetting $setting): mixed
    {
        return $setting->is_encrypted
            ? '********'
            : $this->systemSettingService->castValue($setting);
    }
}
