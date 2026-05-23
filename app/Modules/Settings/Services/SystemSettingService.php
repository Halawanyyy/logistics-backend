<?php

namespace App\Modules\Settings\Services;

use App\Models\User;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Settings\Support\SystemSettingDefaults;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Exceptions\HttpResponseException;

class SystemSettingService
{
    private const CACHE_KEY = 'system_settings.all';

    private static ?bool $settingsTableExists = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = $this->rememberCache()->get($key);

        if (! $setting instanceof SystemSetting) {
            return $default;
        }

        return $this->castValue($setting);
    }

    public function set(string $key, mixed $value, User|int|null $user = null): SystemSetting
    {
        $setting = SystemSetting::query()->where('key', $key)->firstOrFail();

        if (! $setting->is_editable) {
            throw new HttpResponseException(response()->json([
                'message' => 'This setting is not editable.',
            ], 422));
        }

        $setting->update([
            'value' => $this->serializeValue($setting, $value),
            'updated_by' => $user instanceof User ? $user->id : $user,
        ]);

        $this->clearCache();

        return $setting->fresh()->load('updatedBy');
    }

    /**
     * @return Collection<int, SystemSetting>
     */
    public function getGroup(string $group): Collection
    {
        return $this->rememberCache()
            ->filter(fn ($setting) => $setting instanceof SystemSetting && $setting->group === $group)
            ->sortBy(['sort_order', 'key'])
            ->values();
    }

    /**
     * @return Collection<int, SystemSetting>
     */
    public function getPublicSettings(): Collection
    {
        return $this->rememberCache()
            ->filter(fn ($setting) => $setting instanceof SystemSetting && $setting->is_public)
            ->sortBy(['group', 'sort_order', 'key'])
            ->values();
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public function getMany(array $keys): array
    {
        $settings = $this->rememberCache();
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $settings->has($key) ? $this->castValue($settings->get($key)) : null;
        }

        return $values;
    }

    public function castValue(SystemSetting $setting, bool $maskEncrypted = false): mixed
    {
        if ($setting->is_encrypted || $setting->type === 'encrypted') {
            if ($maskEncrypted) {
                return '********';
            }

            if ($setting->value === null) {
                return null;
            }

            try {
                return Crypt::decryptString((string) $setting->value);
            } catch (\Throwable) {
                return null;
            }
        }

        $value = $setting->value;

        if ($value === null) {
            return null;
        }

        return match ($setting->type) {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? in_array($value, ['1', 1, true], true),
            'json' => is_array($value) ? $value : (json_decode((string) $value, true) ?? []),
            'date', 'time', 'string', 'text', 'file', 'image' => $value,
            default => $value,
        };
    }

    public function seedDefaults(): void
    {
        if (! $this->canUseSettings()) {
            return;
        }

        foreach (SystemSettingDefaults::definitions() as $definition) {
            $existing = SystemSetting::withTrashed()->where('key', $definition['key'])->first();

            $value = $existing?->value;

            if ($existing === null) {
                $value = $this->serializeSeedValue($definition);
            } elseif ($existing->value === null) {
                $value = $this->serializeSeedValue($definition);
            }

            SystemSetting::withTrashed()->updateOrCreate(
                ['key' => $definition['key']],
                [
                    'group' => $definition['group'],
                    'value' => $value,
                    'type' => $definition['type'] ?? 'string',
                    'label' => $definition['label'] ?? null,
                    'description' => $definition['description'] ?? null,
                    'is_public' => $definition['is_public'] ?? false,
                    'is_encrypted' => $definition['is_encrypted'] ?? false,
                    'is_editable' => $definition['is_editable'] ?? true,
                    'options' => $definition['options'] ?? null,
                    'validation_rules' => $definition['validation_rules'] ?? null,
                    'sort_order' => $definition['sort_order'] ?? 0,
                    'deleted_at' => null,
                ],
            );
        }

        $this->clearCache();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return Collection<string, SystemSetting>
     */
    public function rememberCache(): Collection
    {
        if (! $this->canUseSettings()) {
            return collect();
        }

        /** @var Collection<string, SystemSetting> $settings */
        $settings = Cache::rememberForever(self::CACHE_KEY, function () {
            return SystemSetting::query()
                ->with('updatedBy')
                ->orderBy('group')
                ->orderBy('sort_order')
                ->orderBy('key')
                ->get()
                ->keyBy('key');
        });

        return $settings;
    }

    public function defaultPaginationLimit(int $fallback = 15): int
    {
        $limit = (int) $this->get('pagination_limit', $fallback);

        return $limit > 0 ? $limit : $fallback;
    }

    private function canUseSettings(): bool
    {
        if (self::$settingsTableExists !== null) {
            return self::$settingsTableExists;
        }

        return self::$settingsTableExists = Schema::hasTable('system_settings');
    }

    private function serializeSeedValue(array $definition): ?string
    {
        $type = $definition['type'] ?? 'string';
        $value = $definition['value'] ?? null;

        if ($value === null) {
            return null;
        }

        if (($definition['is_encrypted'] ?? false) === true || $type === 'encrypted') {
            return Crypt::encryptString((string) $value);
        }

        return $type === 'json'
            ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string) $value;
    }

    private function serializeValue(SystemSetting $setting, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($setting->is_encrypted || $setting->type === 'encrypted') {
            return Crypt::encryptString((string) $value);
        }

        return match ($setting->type) {
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            'decimal' => number_format((float) $value, 2, '.', ''),
            default => (string) $value,
        };
    }
}
