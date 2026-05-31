<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\MerchantIntegrationTokenAuth;
use App\Http\Middleware\SetRequestLocale;
use App\Modules\MerchantPortal\Middleware\EnsureMerchantPortalUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            SetRequestLocale::class,
        ]);

        $middleware->alias([
            'permission' => CheckPermission::class,
            'merchant.integration.token' => MerchantIntegrationTokenAuth::class,
            'merchant.portal' => EnsureMerchantPortalUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
