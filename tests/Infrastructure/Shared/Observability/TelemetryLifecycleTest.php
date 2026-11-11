<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Shared\Observability;

use ArrayObject;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Infrastructure\Shared\Observability\Telemetry;
use Infrastructure\Shared\Observability\TracerProviderInitializer;
use Laravel\Octane\Events\RequestTerminated;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\LaravelTestCase;

/**
 * A batch processor holds spans until something flushes it, as in a long-running worker.
 */
final class TelemetryLifecycleTest extends LaravelTestCase
{
    private const int LARGE_BATCH = 512;

    private const int HOUR_IN_MILLISECONDS = 3_600_000;

    private ?ScopeInterface $scope = null;

    /** @var ArrayObject<int, mixed> */
    private ArrayObject $exportedSpans;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exportedSpans = new ArrayObject();
        $batchProcessor = new BatchSpanProcessor(
            new InMemoryExporter($this->exportedSpans),
            Clock::getDefault(),
            self::LARGE_BATCH,
            self::HOUR_IN_MILLISECONDS,
            30_000,
            self::LARGE_BATCH,
        );
        $tracerProvider = TracerProvider::builder()
            ->addSpanProcessor($batchProcessor)
            ->build();
        $loggerProvider = LoggerProvider::builder()
            ->build();
        $context = Configurator::create()
            ->withTracerProvider($tracerProvider)
            ->withLoggerProvider($loggerProvider)
            ->storeInContext();
        $this->scope = Context::storage()
            ->attach($context);
    }

    protected function tearDown(): void
    {
        $this->scope?->detach();
        parent::tearDown();
    }

    public function test_FlushingSendsTheSpansTheBatchHeld(): void
    {
        $telemetry = $this->app->make(Telemetry::class);
        $telemetry->tracer()
            ->spanBuilder('held')
            ->startSpan()
            ->end();
        $heldBeforeFlush = \count($this->exportedSpans);

        $telemetry->flush();

        self::assertSame(0, $heldBeforeFlush);
        self::assertCount(1, $this->exportedSpans);
    }

    public function test_SpansGoOutAtMostOncePerInterval(): void
    {
        $telemetry = new Telemetry(
            metricExportIntervalMilliseconds: self::HOUR_IN_MILLISECONDS,
            spanExportIntervalMilliseconds: self::HOUR_IN_MILLISECONDS,
        );
        $this->endSpan(telemetry: $telemetry, name: 'first');
        $telemetry->flush();
        $this->endSpan(telemetry: $telemetry, name: 'second');

        $telemetry->flush();

        self::assertCount(1, $this->exportedSpans);
    }

    public function test_TheEndOfAnOctaneRequestFlushes(): void
    {
        $this->app->make(Telemetry::class)
            ->tracer()
            ->spanBuilder('request')
            ->startSpan()
            ->end();

        $requestTerminated = new RequestTerminated(
            $this->app,
            $this->app,
            new Request(),
            new Response(),
        );
        $this->app->make(Dispatcher::class)
            ->dispatch($requestTerminated);

        self::assertCount(1, $this->exportedSpans);
    }

    public function test_TheInitializerRegistersWithTheGlobals(): void
    {
        $initializers = new ReflectionProperty(Globals::class, 'initializers');
        $registeredBefore = \count((array) $initializers->getValue());

        TracerProviderInitializer::register();

        $registeredAfter = \count((array) $initializers->getValue());

        self::assertSame($registeredBefore + 1, $registeredAfter);
    }

    /**
     * @param non-empty-string $name
     */
    private function endSpan(Telemetry $telemetry, string $name): void
    {
        $telemetry->tracer()
            ->spanBuilder($name)
            ->startSpan()
            ->end();
    }
}
