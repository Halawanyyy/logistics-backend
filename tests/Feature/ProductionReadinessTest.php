<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as IlluminateRoute;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    public function test_env_example_documents_production_sensitive_variables(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertIsString($envExample);

        foreach ([
            'APP_TIMEZONE=',
            'FRONTEND_URL=',
            'ADMIN_FRONTEND_URL=',
            'MERCHANT_PORTAL_URL=',
            'SANCTUM_STATEFUL_DOMAINS=',
            'CORS_ALLOWED_ORIGINS=',
            'CORS_SUPPORTS_CREDENTIALS=',
            'SESSION_SECURE_COOKIE=',
            'SESSION_SAME_SITE=',
            'QUEUE_FAILED_DRIVER=',
            'FILESYSTEM_PRIVATE_ROOT=',
            'FILESYSTEM_EXPORTS_ROOT=',
            'FILESYSTEM_ATTACHMENTS_ROOT=',
            'RATE_LIMIT_AUTH_LOGIN_PER_MINUTE=',
            'RATE_LIMIT_PUBLIC_TRACKING_PER_MINUTE=',
            'RATE_LIMIT_INTEGRATION_PER_MINUTE=',
        ] as $requiredEntry) {
            $this->assertStringContainsString($requiredEntry, $envExample);
        }
    }

    public function test_cors_config_is_published_for_api_and_sanctum_routes(): void
    {
        $this->assertSame(['api/*', 'sanctum/csrf-cookie'], config('cors.paths'));
        $this->assertSame(['*'], config('cors.allowed_methods'));
        $this->assertSame(['*'], config('cors.allowed_headers'));
        $this->assertIsArray(config('cors.allowed_origins'));
        $this->assertIsArray(config('cors.allowed_origins_patterns'));
        $this->assertIsBool(config('cors.supports_credentials'));
    }

    public function test_named_rate_limiters_are_attached_to_sensitive_routes(): void
    {
        $this->assertContains(
            'throttle:auth-login',
            $this->findRoute('api/auth/login', 'POST')->gatherMiddleware()
        );

        $this->assertContains(
            'throttle:public-tracking',
            $this->findRoute('api/track/{shipment_number}', 'GET')->gatherMiddleware()
        );

        $integrationMeRoute = $this->findRoute('api/integrations/me', 'GET')->gatherMiddleware();
        $integrationOrdersRoute = $this->findRoute('api/integrations/orders', 'POST')->gatherMiddleware();

        $this->assertContains('merchant.integration.token', $integrationMeRoute);
        $this->assertContains('throttle:integration-api', $integrationMeRoute);
        $this->assertContains('merchant.integration.token', $integrationOrdersRoute);
        $this->assertContains('throttle:integration-api', $integrationOrdersRoute);
    }

    public function test_private_storage_disks_keep_safe_default_roots(): void
    {
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.local.root'));
        $this->assertSame(storage_path('app/exports'), config('filesystems.disks.exports.root'));
        $this->assertSame(storage_path('app/attachments'), config('filesystems.disks.attachments.root'));
    }

    private function findRoute(string $uri, string $method): IlluminateRoute
    {
        $route = collect(app('router')->getRoutes()->getRoutes())->first(
            fn (IlluminateRoute $route): bool => $route->uri() === $uri && in_array($method, $route->methods(), true)
        );

        $this->assertInstanceOf(IlluminateRoute::class, $route, sprintf('Route [%s %s] was not found.', $method, $uri));

        return $route;
    }
}
