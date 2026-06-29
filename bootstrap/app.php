<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')->group(base_path('backend/routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API-only runtime for the migration tool.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Keep Laravel's default exception handling.
    })
    ->create();

$app->useAppPath(base_path('backend/app'));
$app->useDatabasePath(base_path('backend/database'));

return $app;
