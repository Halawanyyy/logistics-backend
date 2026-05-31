<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth-login', function (Request $request): Limit {
            $email = strtolower((string) $request->input('email', 'guest'));

            return Limit::perMinute((int) env('RATE_LIMIT_AUTH_LOGIN_PER_MINUTE', 10))
                ->by($request->ip().'|'.$email);
        });

        RateLimiter::for('public-tracking', function (Request $request): Limit {
            return Limit::perMinute((int) env('RATE_LIMIT_PUBLIC_TRACKING_PER_MINUTE', 120))
                ->by((string) $request->ip());
        });

        RateLimiter::for('integration-api', function (Request $request): Limit {
            $token = $request->attributes->get('merchant_integration_token');
            $plainToken = $request->bearerToken() ?: $request->header('X-Merchant-Token');
            $identifier = $token?->id !== null
                ? 'token:'.$token->id
                : 'fallback:'.hash('sha256', (string) ($plainToken ?: $request->ip()));

            return Limit::perMinute((int) env('RATE_LIMIT_INTEGRATION_PER_MINUTE', 120))
                ->by($identifier);
        });
    }
}
