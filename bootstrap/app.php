<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'setDatabase' => \App\Http\Middleware\SetDatabase::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'apiAuth' => \App\Http\Middleware\ApiAuth::class,
            'printAgentAuth' => \App\Http\Middleware\PrintAgentAuth::class,
        ]);
        $middleware->prepend(\App\Http\Middleware\ForceDynamicAssetUrl::class);
        $middleware->validateCsrfTokens(except: [
            'pos/print-jobs/*',
            'pos/webhooks-jobs/*',
            'pos/sync/push',
            'quota/mp/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            return response()->json([
                'message' => __('Tu sesión ha expirado. Por favor, inicia sesión nuevamente.'),
            ], 419);
        });
    })->create();
