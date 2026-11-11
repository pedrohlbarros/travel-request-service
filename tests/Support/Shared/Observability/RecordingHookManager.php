<?php

declare(strict_types=1);

namespace Tests\Support\Shared\Observability;

use Closure;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\HookManagerInterface;
use Throwable;

final class RecordingHookManager implements HookManagerInterface
{
    /** @var array<string, ?Closure(object|string|null, array<mixed>, mixed, ?Throwable): mixed> by "class::function" */
    public array $postHooks = [];

    /**
     * @param ?Closure(object|string|null, array<mixed>, ?string, string, ?string, ?int): (array<mixed>|null|void) $preHook
     * @param ?Closure(object|string|null, array<mixed>, mixed, ?Throwable): mixed $postHook
     */
    public function hook(?string $class, string $function, ?Closure $preHook = null, ?Closure $postHook = null): void
    {
        $this->postHooks[$class . '::' . $function] = $postHook;
    }
}
