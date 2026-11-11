<?php

declare(strict_types=1);

namespace Tests\Support\Shared\Observability;

use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\TracerInterface;

/**
 * Asks the switchable provider for the current tracer on every span, never keeping one.
 */
final readonly class SwitchableTracer implements TracerInterface
{
    public function __construct(
        private SwitchableTracerProvider $provider,
        private string $name,
        private ?string $version,
        private ?string $schemaUrl,
    ) {}

    public function spanBuilder(string $spanName): SpanBuilderInterface
    {
        return $this->provider->delegate()
            ->getTracer($this->name, $this->version, $this->schemaUrl)
            ->spanBuilder($spanName);
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
