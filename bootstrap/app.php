<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'device_token' => \App\Http\Middleware\AuthenticateDeviceToken::class,
            'normalize_json' => \App\Http\Middleware\NormalizeJsonRequest::class,
        ]);

        // Trust proxies when behind load balancer
        if (env('TRUST_PROXIES', false)) {
            $middleware->trustProxies(
                at: env('TRUST_PROXIES') === '*' ? '*' : explode(',', env('TRUST_PROXIES', '')),
                headers: Request::HEADER_X_FORWARDED_FOR |
                    Request::HEADER_X_FORWARDED_HOST |
                    Request::HEADER_X_FORWARDED_PORT |
                    Request::HEADER_X_FORWARDED_PROTO
            );
        }

        // Add security headers
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Suppress broken pipe notices from server.php stdout writes
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            // Suppress broken pipe notices from server.php
            if ($errno === E_NOTICE && str_contains($errstr, 'Broken pipe') && str_contains($errfile, 'server.php')) {
                return true; // Suppress this error
            }

            return false; // Let other errors through
        }, E_NOTICE);

        // Don't report certain exceptions in production
        $exceptions->dontReport([
            \Illuminate\Auth\AuthenticationException::class,
            \Illuminate\Auth\Access\AuthorizationException::class,
            \Illuminate\Database\Eloquent\ModelNotFoundException::class,
            \Illuminate\Validation\ValidationException::class,
        ]);
    })->create();
