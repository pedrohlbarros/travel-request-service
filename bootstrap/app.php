<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Interfaces\Http\Shared\Middleware\AssignRequestContext;
use Interfaces\Http\Shared\Middleware\RecordRequestMetrics;
use Interfaces\Http\Shared\Problem\ClientErrorReporter;
use Interfaces\Http\Shared\Problem\ProblemDetailsRenderer;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        apiPrefix: 'api/v1',
        then: static function (): void {
            Route::middleware('api')
                ->group(__DIR__ . '/../routes/health.php');
        },
    )
    ->withMiddleware(static function (Middleware $middleware): void {
        $middleware->prepend([AssignRequestContext::class, RecordRequestMetrics::class]);
        $middleware->redirectGuestsTo(static fn(): ?string => null);
    })
    ->withExceptions(static function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(static fn(): bool => true);
        $exceptions->report(static function (Throwable $exception): bool {
            $reporter = app(ClientErrorReporter::class);

            return $reporter->report(exception: $exception);
        });
        $exceptions->render(static function (Throwable $exception, Request $request): JsonResponse {
            $renderer = app(ProblemDetailsRenderer::class);

            return $renderer->render(exception: $exception, request: $request);
        });
    })
    ->create()
    ->useAppPath(dirname(__DIR__) . '/src/Infrastructure');
