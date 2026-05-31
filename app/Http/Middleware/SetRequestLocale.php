<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetRequestLocale
{
    /**
     * @var list<string>
     */
    private array $supportedLocales = ['en', 'ar'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);
        $request->setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $fallback = $this->supportedFallbackLocale();

        $headerLocale = $this->normalizeLocale($request->header('X-Locale'));

        if ($headerLocale !== null) {
            return $headerLocale;
        }

        $acceptLanguage = $request->header('Accept-Language');

        if (! is_string($acceptLanguage) || trim($acceptLanguage) === '') {
            return $fallback;
        }

        foreach (explode(',', $acceptLanguage) as $candidate) {
            $locale = $this->normalizeLocale($candidate);

            if ($locale !== null) {
                return $locale;
            }
        }

        return $fallback;
    }

    private function normalizeLocale(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $segment = strtolower(trim(explode(';', $value)[0]));
        $baseLocale = explode('-', $segment)[0];
        $baseLocale = explode('_', $baseLocale)[0];

        return in_array($baseLocale, $this->supportedLocales, true)
            ? $baseLocale
            : null;
    }

    private function supportedFallbackLocale(): string
    {
        $fallback = (string) config('app.fallback_locale', 'en');
        $normalizedFallback = $this->normalizeLocale($fallback);

        return $normalizedFallback ?? 'en';
    }
}
