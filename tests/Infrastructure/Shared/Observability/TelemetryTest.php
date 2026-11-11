<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Shared\Observability;

use DateTimeImmutable;
use Infrastructure\Shared\Observability\ProcessMetrics;
use Infrastructure\Shared\Observability\Telemetry;
use Infrastructure\Shared\Observability\TraceContextLogProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Shared\Observability\CapturesTelemetry;

final class TelemetryTest extends TestCase
{
    use CapturesTelemetry;

    private const string TRACEPARENT = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

    private const string TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    private const string PARENT_SPAN_ID = '00f067aa0ba902b7';

    private const int LONG_INTERVAL_MILLISECONDS = 3_600_000;

    protected function tearDown(): void
    {
        $this->releaseTelemetry();
        parent::tearDown();
    }

    public function test_RunsTheWorkInASpanThatContinuesTheGivenTrace(): void
    {
        $this->captureTelemetry();
        $telemetry = $this->telemetry();
        $parent = $telemetry->contextFrom(carrier: ['traceparent' => self::TRACEPARENT]);

        $result = $telemetry->inSpan(
            name: 'work',
            work: static fn(SpanInterface $span): string => 'done',
            kind: SpanKind::KIND_CONSUMER,
            attributes: ['work.kind' => 'test'],
            parent: $parent,
        );

        $span = $this->capturedSpan(name: 'work');
        $workKind = $span->getAttributes()
            ->get('work.kind');
        $statusCode = $span->getStatus()
            ->getCode();

        self::assertSame('done', $result);
        self::assertSame(SpanKind::KIND_CONSUMER, $span->getKind());
        self::assertSame(self::TRACE_ID, $span->getTraceId());
        self::assertSame(self::PARENT_SPAN_ID, $span->getParentSpanId());
        self::assertSame('test', $workKind);
        self::assertSame(StatusCode::STATUS_UNSET, $statusCode);
    }

    public function test_AFailureIsRecordedOnTheSpanAndLetThrough(): void
    {
        $this->captureTelemetry();

        $failure = null;

        try {
            $this->telemetry()
                ->inSpan(
                    name: 'failing work',
                    work: static fn(SpanInterface $span): string => throw new RuntimeException('It broke.'),
                );
        } catch (RuntimeException $thrown) {
            $failure = $thrown;
        }

        $span = $this->capturedSpan(name: 'failing work');
        $status = $span->getStatus();

        self::assertInstanceOf(RuntimeException::class, $failure);
        self::assertSame(StatusCode::STATUS_ERROR, $status->getCode());
        self::assertSame('It broke.', $status->getDescription());
        self::assertSame('exception', $span->getEvents()[0]->getName());
    }

    public function test_TheCurrentSpanTravelsAsATraceparent(): void
    {
        $this->captureTelemetry();
        $telemetry = $this->telemetry();

        $headers = $telemetry->inSpan(
            name: 'outer',
            work: static fn(SpanInterface $span): array => $telemetry->propagationHeaders(),
        );
        $outsideAnySpan = $telemetry->propagationHeaders();

        $span = $this->capturedSpan(name: 'outer');

        self::assertStringStartsWith("00-{$span->getTraceId()}-{$span->getSpanId()}-", $headers['traceparent']);
        self::assertSame([], $outsideAnySpan);
    }

    public function test_MetricsGoOutAtMostOncePerInterval(): void
    {
        $this->captureTelemetry();
        $telemetry = new Telemetry(
            metricExportIntervalMilliseconds: self::LONG_INTERVAL_MILLISECONDS,
            spanExportIntervalMilliseconds: self::LONG_INTERVAL_MILLISECONDS,
        );
        $counter = $telemetry->meter()
            ->createCounter('test.counter');
        $counter->add(1);

        $telemetry->flush();
        $exportedFirst = \count($this->metricExporter->collect(true));
        $counter->add(1);
        $telemetry->flush();
        $exportedAgain = \count($this->metricExporter->collect(true));

        self::assertSame(1, $exportedFirst);
        self::assertSame(0, $exportedAgain);
    }

    public function test_FlushingWithoutTheSdkDoesNothing(): void
    {
        $telemetry = $this->telemetry();

        $telemetry->flush();

        self::assertSame([], $telemetry->propagationHeaders());
    }

    public function test_EveryProcessReportsItsMemory(): void
    {
        $this->captureTelemetry();

        ProcessMetrics::register(telemetry: $this->telemetry());

        self::assertGreaterThan(0, $this->capturedValue(metricName: 'process.memory.usage'));
        self::assertGreaterThan(0, $this->capturedValue(metricName: 'process.memory.peak'));
    }

    public function test_LogLinesCarryTheCurrentTraceAndSpan(): void
    {
        $this->captureTelemetry();
        $record = new LogRecord(
            new DateTimeImmutable(),
            'app',
            Level::Info,
            'Something happened.',
        );
        $processor = new TraceContextLogProcessor();

        $inSpan = $this->telemetry()
            ->inSpan(name: 'logging', work: static fn(SpanInterface $span): LogRecord => $processor($record));
        $outsideAnySpan = $processor($record);

        $span = $this->capturedSpan(name: 'logging');

        self::assertSame(['trace_id' => $span->getTraceId(), 'span_id' => $span->getSpanId()], $inSpan->extra);
        self::assertSame([], $outsideAnySpan->extra);
    }

    private function telemetry(): Telemetry
    {
        return new Telemetry(metricExportIntervalMilliseconds: 1, spanExportIntervalMilliseconds: 1);
    }
}
