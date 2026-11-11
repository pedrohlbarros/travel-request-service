<?php

declare(strict_types=1);

namespace Tests\Support\Shared\Observability;

use ArrayObject;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Metrics\Data\Gauge;
use OpenTelemetry\SDK\Metrics\Data\Histogram;
use OpenTelemetry\SDK\Metrics\Data\HistogramDataPoint;
use OpenTelemetry\SDK\Metrics\Data\NumberDataPoint;
use OpenTelemetry\SDK\Metrics\Data\Sum;
use OpenTelemetry\SDK\Metrics\Data\Temporality;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MetricExporter\InMemoryExporter as InMemoryMetricExporter;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter as InMemorySpanExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

/**
 * Swaps the global providers for in-memory ones, so a test reads the spans and metrics the code produced
 * without any Collector running. Released after the test, back to the no-op providers. Spans go through the
 * suite's switchable tracer provider, since the auto-instrumentation keeps the first tracer it is given.
 */
trait CapturesTelemetry
{
    private const string SDK_DISABLED = 'OTEL_SDK_DISABLED';

    private ?ScopeInterface $telemetryScope = null;

    private InMemorySpanExporter $spanExporter;

    private InMemoryMetricExporter $metricExporter;

    private MeterProviderInterface $capturedMeterProvider;

    protected function captureTelemetry(): void
    {
        self::setSdkDisabled(isDisabled: false);
        $this->spanExporter = new InMemorySpanExporter();
        $this->metricExporter = new InMemoryMetricExporter(new ArrayObject(), Temporality::CUMULATIVE);
        $tracerProvider = TracerProvider::builder()
            ->addSpanProcessor(new SimpleSpanProcessor($this->spanExporter))
            ->build();
        $this->capturedMeterProvider = MeterProvider::builder()
            ->addReader(new ExportingReader($this->metricExporter))
            ->build();
        $loggerProvider = LoggerProvider::builder()
            ->build();
        $switchableTracerProvider = SwitchableTracerProvider::instance();
        $switchableTracerProvider->switchTo(delegate: $tracerProvider);
        $capturingContext = Configurator::create()
            ->withTracerProvider($switchableTracerProvider)
            ->withMeterProvider($this->capturedMeterProvider)
            ->withLoggerProvider($loggerProvider)
            ->withPropagator(TraceContextPropagator::getInstance())
            ->storeInContext();
        $this->telemetryScope = Context::storage()
            ->attach($capturingContext);
    }

    protected function releaseTelemetry(): void
    {
        $this->telemetryScope?->detach();
        $this->telemetryScope = null;
        SwitchableTracerProvider::instance()
            ->switchOff();
        self::setSdkDisabled(isDisabled: true);
    }

    /**
     * The SDK hands out no-op meters while OTEL_SDK_DISABLED is true, which is the suite's setting.
     */
    private static function setSdkDisabled(bool $isDisabled): void
    {
        $value = $isDisabled ? 'true' : 'false';
        putenv(self::SDK_DISABLED . '=' . $value);
        $_ENV[self::SDK_DISABLED] = $value;
        $_SERVER[self::SDK_DISABLED] = $value;
    }

    /**
     * @return list<ImmutableSpan>
     */
    protected function capturedSpans(): array
    {
        $spans = [];

        foreach ($this->spanExporter->getSpans() as $span) {
            \assert($span instanceof ImmutableSpan);
            $spans[] = $span;
        }

        return $spans;
    }

    protected function capturedSpan(string $name): ImmutableSpan
    {
        foreach ($this->capturedSpans() as $span) {
            if ($span->getName() === $name) {
                return $span;
            }
        }

        self::fail(\sprintf('No span named "%s".', $name));
    }

    /**
     * Collects now and returns the current points of the metric, one per attribute set.
     *
     * @return list<NumberDataPoint|HistogramDataPoint>
     */
    protected function capturedPoints(string $metricName): array
    {
        $this->metricExporter->collect(true);
        $this->capturedMeterProvider->forceFlush();
        $points = [];

        foreach ($this->metricExporter->collect(true) as $metric) {
            if ($metric->name !== $metricName) {
                continue;
            }

            $pointData = $metric->data;
            \assert($pointData instanceof Sum || $pointData instanceof Gauge || $pointData instanceof Histogram);

            foreach ($pointData->dataPoints as $point) {
                $points[] = $point;
            }
        }

        return $points;
    }

    /**
     * @param array<string, bool|int|float|string> $attributes
     */
    protected function capturedValue(string $metricName, array $attributes = []): int|float|null
    {
        ksort($attributes);

        foreach ($this->capturedPoints(metricName: $metricName) as $point) {
            $pointAttributes = $point->attributes->toArray();
            ksort($pointAttributes);

            if ($point instanceof NumberDataPoint && $pointAttributes === $attributes) {
                return $point->value;
            }
        }

        return null;
    }
}
