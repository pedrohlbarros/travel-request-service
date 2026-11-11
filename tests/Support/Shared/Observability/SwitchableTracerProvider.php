<?php

declare(strict_types=1);

namespace Tests\Support\Shared\Observability;

use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;

/**
 * The auto-instrumentation keeps the first tracer it gets for the life of the process, so the suite gives it
 * this provider once and points it at each test's in-memory provider.
 */
final class SwitchableTracerProvider implements TracerProviderInterface
{
    private static ?self $instance = null;

    private TracerProviderInterface $delegate;

    private function __construct()
    {
        $this->delegate = new NoopTracerProvider();
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function switchTo(TracerProviderInterface $delegate): void
    {
        $this->delegate = $delegate;
    }

    public function switchOff(): void
    {
        $this->delegate = new NoopTracerProvider();
    }

    /**
     * @param iterable<mixed> $attributes
     */
    public function getTracer(
        string $name,
        ?string $version = null,
        ?string $schemaUrl = null,
        iterable $attributes = [],
    ): TracerInterface {
        return new SwitchableTracer(
            provider: $this,
            name: $name,
            version: $version,
            schemaUrl: $schemaUrl,
        );
    }

    public function delegate(): TracerProviderInterface
    {
        return $this->delegate;
    }
}
