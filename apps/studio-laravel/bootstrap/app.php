<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['api/*']);
        $middleware->alias([
            'studio.auth' => \App\Http\Middleware\StudioBasicAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $exception): void {
            if (app()->runningInConsole()) {
                return;
            }

            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            if ($status < 500) {
                return;
            }

            $request = request();
            app(\App\Services\PlatformTelemetry::class)->record([
                'severity' => 'error',
                'source' => 'laravel',
                'event_type' => 'server_exception',
                'route' => $request->path(),
                'method' => $request->method(),
                'status_code' => $status,
                'request_id' => $request->header('X-Request-ID'),
                'error_class' => $exception::class,
                'message' => $exception->getMessage(),
                'stack_trace' => $exception->getTraceAsString(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
