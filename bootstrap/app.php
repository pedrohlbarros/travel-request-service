<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Interfaces\Http\Shared\Middleware\AssignRequestContext;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        apiPrefix: 'api/v1',
        then: static function (): void {
            Route::middleware('api')->group(__DIR__ . '/../routes/health.php');
        },
    )
    ->withMiddleware(static function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestContext::class);
    })
    ->withExceptions(static function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(static fn(): bool => true);
    })->create()
    ->useAppPath(dirname(__DIR__) . '/src/Infrastructure');
