<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Observability;

use Illuminate\Foundation\Application;
use OpenTelemetry\API\Configuration\ConfigProperties;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\Context;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\HookManagerInterface;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\Instrumentation;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Hooks\Illuminate\Contracts\Http\Kernel as HttpKernelHook;
use OpenTelemetry\Contrib\Instrumentation\Laravel\LaravelConfiguration;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Watchers\CacheWatcher;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Watchers\ExceptionWatcher;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Watchers\LogWatcher;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Watchers\QueryWatcher;

/**
 * The part of the Laravel auto-instrumentation worth its cost: the HTTP server span, one span per query,
 * cache hits and misses as span events, exceptions and logs. The package's own registration is disabled,
 * because it also adds a span per Eloquent operation, repeating the query span under it, and one per Redis
 * command.
 */
final readonly class LaravelHttpAndQueryInstrumentation implements Instrumentation
{
    public function register(HookManagerInterface $hookManager, ConfigProperties $configuration, Context $context): void
    {
        new HttpKernelHook()
            ->instrument(new LaravelConfiguration(), $hookManager, $context);
        $hookManager->hook(
            Application::class,
            '__construct',
            postHook: static function (object|string|null $application) use ($context): void {
                \assert($application instanceof Application);
                new CacheWatcher()
                    ->register($application);
                new ExceptionWatcher()
                    ->register($application);
                new LogWatcher($context)
                    ->register($application);
                new QueryWatcher($context)
                    ->register($application);
            },
        );
    }
}
