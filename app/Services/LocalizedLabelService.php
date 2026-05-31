<?php

namespace App\Services;

use Illuminate\Support\Str;

class LocalizedLabelService
{
    public function label(string $group, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || $value === '') {
            return $value;
        }

        $translationKey = 'enums.'.$group.'.'.$value;
        $translation = __($translationKey);

        if ($translation !== $translationKey) {
            return $translation;
        }

        return Str::of($value)
            ->replace('_', ' ')
            ->headline()
            ->toString();
    }

    /**
     * @return array<string, string>
     */
    public function labels(string $group): array
    {
        $labels = trans('enums.'.$group);

        return is_array($labels) ? $labels : [];
    }
}
