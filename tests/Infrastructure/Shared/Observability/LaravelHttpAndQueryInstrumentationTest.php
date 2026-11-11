<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Shared\Observability;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Redis\Events\CommandExecuted;
use Infrastructure\Shared\Observability\LaravelHttpAndQueryInstrumentation;
use OpenTelemetry\API\Configuration\Noop\NoopConfigProperties;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\Context;
use PHPUnit\Framework\TestCase;
use Tests\Support\Shared\Observability\RecordingHookManager;

final class LaravelHttpAndQueryInstrumentationTest extends TestCase
{
    public function test_HooksTheHttpKernelAndTheApplication(): void
    {
        $hooks = new RecordingHookManager();

        new LaravelHttpAndQueryInstrumentation()
            ->register(hookManager: $hooks, configuration: new NoopConfigProperties(), context: new Context());

        self::assertSame(
            [Kernel::class . '::handle', Application::class . '::__construct'],
            array_keys($hooks->postHooks),
        );
    }

    public function test_WatchesQueriesAndLogsButNotRedisCommands(): void
    {
        $hooks = new RecordingHookManager();
        new LaravelHttpAndQueryInstrumentation()
            ->register(hookManager: $hooks, configuration: new NoopConfigProperties(), context: new Context());
        $application = new Application(sys_get_temp_dir());
        $applicationHook = $hooks->postHooks[Application::class . '::__construct'];
        \assert($applicationHook !== null);

        $applicationHook(
            $application,
            [],
            null,
            null,
        );

        $events = $application->make(Dispatcher::class);

        self::assertTrue($events->hasListeners(QueryExecuted::class));
        self::assertTrue($events->hasListeners(MessageLogged::class));
        self::assertFalse($events->hasListeners(CommandExecuted::class));
    }
}
