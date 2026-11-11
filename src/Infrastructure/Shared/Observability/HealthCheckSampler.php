<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Observability;

use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Trace\LinkInterface;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SamplingResult;

/**
 * Samples everything (parent based) except the health checks the container runtime calls every few seconds,
 * which would bury the real traces.
 */
final readonly class HealthCheckSampler implements SamplerInterface
{
    public const string HEALTH_PATH_PREFIX = '/health/';

    private const string URL_PATH = 'url.path';

    private SamplerInterface $sampler;

    public function __construct()
    {
        $this->sampler = new ParentBased(new AlwaysOnSampler());
    }

    /**
     * @param AttributesInterface<mixed, mixed> $attributes
     * @param list<LinkInterface> $links
     */
    public function shouldSample(
        ContextInterface $parentContext,
        string $traceId,
        string $spanName,
        int $spanKind,
        AttributesInterface $attributes,
        array $links,
    ): SamplingResult {
        $hasParent = Span::fromContext($parentContext)
            ->getContext()
            ->isValid();
        $path = $attributes->get(self::URL_PATH);
        $isHealthCheck = $spanKind === SpanKind::KIND_SERVER
            && \is_string($path)
            && str_starts_with($path, self::HEALTH_PATH_PREFIX);

        if (! $hasParent && $isHealthCheck) {
            return new SamplingResult(SamplingResult::DROP);
        }

        return $this->sampler->shouldSample(
            $parentContext,
            $traceId,
            $spanName,
            $spanKind,
            $attributes,
            $links,
        );
    }

    public function getDescription(): string
    {
        return 'HealthCheckSampler{' . $this->sampler->getDescription() . '}';
    }
}
