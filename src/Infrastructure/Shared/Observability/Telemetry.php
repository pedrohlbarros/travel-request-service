<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Observability;

use Closure;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface as SdkLoggerProvider;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface as SdkMeterProvider;
use OpenTelemetry\SDK\Trace\TracerProviderInterface as SdkTracerProvider;
use Throwable;

/**
 * The one way into OpenTelemetry for our own code. Everything goes through the global providers, which the SDK
 * builds lazily in each process from the OTEL_* settings, or which are no-ops when the SDK is disabled.
 *
 * Long-running processes never reach PHP's shutdown, so each unit of work (a request, an outbox batch, a
 * message) flushes spans and logs; metrics go out at most once per export interval.
 */
final class Telemetry
{
    public const string INSTRUMENTATION_SCOPE = 'travel-request-service';

    private const int MILLISECONDS_PER_SECOND = 1000;

    private ?float $lastMetricExportAt = null;

    public function __construct(
        private readonly int $metricExportIntervalMilliseconds,
    ) {}

    public function tracer(): TracerInterface
    {
        return Globals::tracerProvider()
            ->getTracer(self::INSTRUMENTATION_SCOPE);
    }

    public function meter(): MeterInterface
    {
        return Globals::meterProvider()
            ->getMeter(self::INSTRUMENTATION_SCOPE);
    }

    /**
     * Runs the work inside a span, recording a failure on it before letting the exception through.
     *
     * @template T
     *
     * @param non-empty-string $name
     * @param Closure(SpanInterface): T $work
     * @param SpanKind::KIND_* $kind
     * @param array<non-empty-string, bool|int|float|string> $attributes
     *
     * @return T
     */
    public function inSpan(
        string $name,
        Closure $work,
        int $kind = SpanKind::KIND_INTERNAL,
        array $attributes = [],
        ?ContextInterface $parent = null,
    ): mixed {
        $span = $this->tracer()
            ->spanBuilder($name)
            ->setSpanKind($kind)
            ->setParent($parent ?? Context::getCurrent())
            ->setAttributes($attributes)
            ->startSpan();
        $scope = $span->activate();

        try {
            return $work($span);
        } catch (Throwable $failure) {
            $span->recordException($failure);
            $span->setStatus(StatusCode::STATUS_ERROR, $failure->getMessage());

            throw $failure;
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    /**
     * @param array<string, string> $carrier message headers holding a traceparent
     */
    public function contextFrom(array $carrier): ContextInterface
    {
        return Globals::propagator()
            ->extract($carrier);
    }

    /**
     * The headers that carry the current trace on to another process; empty when there is none.
     *
     * @return array<string, string>
     */
    public function propagationHeaders(?ContextInterface $context = null): array
    {
        $carrier = [];
        Globals::propagator()
            ->inject($carrier, null, $context);
        \assert(\is_array($carrier));
        $headers = [];

        foreach ($carrier as $name => $value) {
            \assert(\is_string($name) && \is_string($value));
            $headers[$name] = $value;
        }

        return $headers;
    }

    public function flush(): void
    {
        $tracerProvider = Globals::tracerProvider();
        $loggerProvider = Globals::loggerProvider();

        if ($tracerProvider instanceof SdkTracerProvider) {
            $tracerProvider->forceFlush();
        }

        if ($loggerProvider instanceof SdkLoggerProvider) {
            $loggerProvider->forceFlush();
        }

        $this->flushMetricsWhenDue();
    }

    private function flushMetricsWhenDue(): void
    {
        $meterProvider = Globals::meterProvider();
        $now = microtime(true);
        $intervalSeconds = $this->metricExportIntervalMilliseconds / self::MILLISECONDS_PER_SECOND;
        $isDue = $this->lastMetricExportAt === null || $now - $this->lastMetricExportAt >= $intervalSeconds;

        if (! $isDue || ! $meterProvider instanceof SdkMeterProvider) {
            return;
        }

        $meterProvider->forceFlush();
        $this->lastMetricExportAt = $now;
    }
}
