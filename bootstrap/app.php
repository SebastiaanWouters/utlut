<?php

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
        $middleware->alias([
            'device_token' => \App\Http\Middleware\AuthenticateDeviceToken::class,
        ]);
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
    })->create();
