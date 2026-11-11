<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Shared\Observability;

use Infrastructure\Shared\Observability\HealthCheckSampler;
use Infrastructure\Shared\Observability\TracerProviderInitializer;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Instrumentation\ContextKeys;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Trace\SamplingResult;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;

final class HealthCheckSamplerTest extends TestCase
{
    private const string TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    private const string TRACEPARENT = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

    private const string SDK_DISABLED = 'OTEL_SDK_DISABLED';

    protected function tearDown(): void
    {
        self::setSdkDisabled(value: 'true');
        parent::tearDown();
    }

    public function test_DropsTheHealthChecksThatStartATrace(): void
    {
        $decision = $this->decisionFor(path: '/health/live', kind: SpanKind::KIND_SERVER, parent: Context::getRoot());

        self::assertSame(SamplingResult::DROP, $decision);
    }

    public function test_SamplesEveryOtherRequest(): void
    {
        $decision = $this->decisionFor(
            path: '/api/v1/travel-requests',
            kind: SpanKind::KIND_SERVER,
            parent: Context::getRoot(),
        );

        self::assertSame(SamplingResult::RECORD_AND_SAMPLE, $decision);
    }

    public function test_AHealthCheckInsideAnotherTraceFollowsItsParent(): void
    {
        $parent = TraceContextPropagator::getInstance()
            ->extract(['traceparent' => self::TRACEPARENT]);

        $decision = $this->decisionFor(path: '/health/ready', kind: SpanKind::KIND_SERVER, parent: $parent);

        self::assertSame(SamplingResult::RECORD_AND_SAMPLE, $decision);
    }

    public function test_SpansThatAreNotServerRequestsAreSampled(): void
    {
        $decision = $this->decisionFor(path: '/health/live', kind: SpanKind::KIND_INTERNAL, parent: Context::getRoot());

        self::assertSame(SamplingResult::RECORD_AND_SAMPLE, $decision);
    }

    public function test_DescribesItselfAroundTheParentBasedSampler(): void
    {
        $description = new HealthCheckSampler()
            ->getDescription();

        self::assertStringStartsWith('HealthCheckSampler{ParentBased', $description);
    }

    public function test_TheInitializerLeavesADisabledSdkAlone(): void
    {
        $configurator = Configurator::create();

        $initialized = TracerProviderInitializer::initialize(configurator: $configurator);

        self::assertSame($configurator, $initialized);
    }

    public function test_TheInitializerInstallsATracerProviderWithTheSampler(): void
    {
        self::setSdkDisabled(value: 'false');

        $context = TracerProviderInitializer::initialize(configurator: Configurator::create())
            ->storeInContext();
        $tracerProvider = $context->get(ContextKeys::tracerProvider());
        \assert($tracerProvider instanceof TracerProvider);
        $description = $tracerProvider->getSampler()
            ->getDescription();

        self::assertStringStartsWith('HealthCheckSampler', $description);
    }

    private function decisionFor(string $path, int $kind, ContextInterface $parent): int
    {
        $sampler = new HealthCheckSampler();
        $attributes = Attributes::create(['url.path' => $path]);

        $result = $sampler->shouldSample(
            parentContext: $parent,
            traceId: self::TRACE_ID,
            spanName: 'GET',
            spanKind: $kind,
            attributes: $attributes,
            links: [],
        );

        return $result->getDecision();
    }

    private static function setSdkDisabled(string $value): void
    {
        putenv(self::SDK_DISABLED . '=' . $value);
        $_ENV[self::SDK_DISABLED] = $value;
        $_SERVER[self::SDK_DISABLED] = $value;
    }
}
