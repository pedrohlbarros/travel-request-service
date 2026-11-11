<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Notification\Console;

use Application\Shared\Port\Clock;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Console\Signals;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Notification\Messaging\DecisionMessageHandler;
use Infrastructure\Notification\Persistence\DatabaseProcessedEvents;
use Infrastructure\Notification\Persistence\NotificationRows;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Metrics\Data\HistogramDataPoint;
use Tests\Support\LaravelTestCase;
use Tests\Support\Notification\Fixture\DecisionMother;
use Tests\Support\Notification\Fixture\UsesDecisionQueues;
use Tests\Support\Shared\Observability\CapturesTelemetry;
use Tests\Support\Shared\Port\FrozenClock;

final class ConsumeDecisionsCommandTest extends LaravelTestCase
{
    use CapturesTelemetry;
    use RefreshDatabase;
    use UsesDecisionQueues;

    private const string ONE_SHORT_RETRY = '100';

    private const string TRACEPARENT = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

    private const string TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    private const string PARENT_SPAN_ID = '00f067aa0ba902b7';

    private const string LONG_RETRY = '60000';

    private const int RETRY_WAIT_MICROSECONDS = 600_000;

    private const int UNUSED_PORT = 1;

    private const int WAIT_MILLISECONDS = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(Config::class)
            ->set('notification.consumer.wait_ms', self::WAIT_MILLISECONDS);
        $this->useRetryDelays(retryDelaysMilliseconds: self::ONE_SHORT_RETRY);
        $this->declareDecisionQueues();
    }

    protected function tearDown(): void
    {
        $this->closeDecisionQueues();
        $this->releaseTelemetry();
        parent::tearDown();
    }

    public function test_DeliversADecisionAndSettlesTheMessage(): void
    {
        $this->publishApproval();

        $exitCode = $this->consumeUntilEmpty();

        $mainQueue = $this->decisionSettings()->queue;

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(1, $this->rowsIn(table: NotificationRows::TABLE));
        self::assertSame(1, $this->rowsIn(table: DatabaseProcessedEvents::TABLE));
        self::assertSame([], $this->messagesIn(queue: $mainQueue));
        self::assertSame([], $this->deadLetters());
    }

    public function test_TheDeliveryContinuesTheTraceTheMessageCarries(): void
    {
        $this->captureTelemetry();
        $this->publishApproval(headers: [
            'traceparent' => self::TRACEPARENT,
            DecisionMessageHandler::REQUEST_ID_HEADER => 'request-id-of-the-decision',
        ]);

        $this->consumeUntilEmpty();

        $consumeSpan = $this->capturedSpan(name: 'process ' . $this->decisionSettings()->queue);
        $deliverSpan = $this->capturedSpan(name: 'notification.deliver');
        $durations = $this->capturedPoints(metricName: 'notification.delivery.duration');

        self::assertSame(SpanKind::KIND_CONSUMER, $consumeSpan->getKind());
        self::assertSame(self::TRACE_ID, $consumeSpan->getTraceId());
        self::assertSame(self::PARENT_SPAN_ID, $consumeSpan->getParentSpanId());
        self::assertSame($consumeSpan->getSpanId(), $deliverSpan->getParentSpanId());
        self::assertSame(
            1,
            $this->capturedValue(metricName: 'notification.deliveries', attributes: ['outcome' => 'delivered']),
        );
        self::assertCount(1, $durations);
    }

    public function test_RecordsTheTimeFromTheDecisionToItsDelivery(): void
    {
        $decidedAt = new DateTimeImmutable(DecisionMother::DECIDED_AT);
        $deliveredAt = $decidedAt->modify('+3 seconds');
        $this->app->instance(Clock::class, new FrozenClock(frozenAt: $deliveredAt));
        $this->captureTelemetry();
        $this->publishApproval();
        $this->publishApproval();

        $this->consumeUntilEmpty();

        $endToEnd = $this->capturedPoints(metricName: 'notification.end_to_end.duration');

        self::assertCount(1, $endToEnd);
        self::assertInstanceOf(HistogramDataPoint::class, $endToEnd[0]);
        self::assertSame(1, $endToEnd[0]->count);
        self::assertEqualsWithDelta(3.0, $endToEnd[0]->sum, 0.001);
    }

    public function test_EveryOutcomeIsCounted(): void
    {
        $this->captureTelemetry();
        $this->publishApproval();
        $this->publishApproval();
        $this->publishDecision(body: '{not json');

        $this->consumeUntilEmpty();

        self::assertSame(
            1,
            $this->capturedValue(metricName: 'notification.deliveries', attributes: ['outcome' => 'delivered']),
        );
        self::assertSame(
            1,
            $this->capturedValue(metricName: 'notification.deliveries', attributes: ['outcome' => 'duplicate']),
        );
        self::assertSame(
            1,
            $this->capturedValue(metricName: 'notification.deliveries', attributes: ['outcome' => 'dead_lettered']),
        );
    }

    public function test_ARedeliveredDecisionNotifiesOnlyOnce(): void
    {
        $this->publishApproval();
        $this->publishApproval();

        $this->consumeUntilEmpty();

        self::assertSame(1, $this->rowsIn(table: NotificationRows::TABLE));
    }

    public function test_AMessageThatIsNotADecisionIsDeadLetteredAtOnce(): void
    {
        $this->publishDecision(body: '{not json');

        $this->consumeUntilEmpty();

        $deadLetters = $this->deadLetters();
        $deadLetter = $deadLetters[0];

        self::assertCount(1, $deadLetters);
        self::assertSame(
            DecisionMessageHandler::UNREADABLE,
            self::headerOf(message: $deadLetter, header: DecisionMessageHandler::DEATH_REASON_HEADER),
        );
        self::assertSame(
            'The decision message cannot be read: the body is not JSON.',
            self::headerOf(message: $deadLetter, header: DecisionMessageHandler::LAST_ERROR_HEADER),
        );
        self::assertSame('{not json', $deadLetter->getBody());
        self::assertSame([], $this->waitingForRetry(retry: 1));
    }

    public function test_ADecisionWithInvalidDataIsDeadLetteredToo(): void
    {
        $this->publishApproval(dataOverrides: [
            'requester' => ['id' => 'not-a-uuid', 'name' => 'Luffy', 'email' => 'luffy@example.com'],
        ]);

        $this->consumeUntilEmpty();

        $deadLetters = $this->deadLetters();

        self::assertCount(1, $deadLetters);
        self::assertSame(
            DecisionMessageHandler::UNREADABLE,
            self::headerOf(message: $deadLetters[0], header: DecisionMessageHandler::DEATH_REASON_HEADER),
        );
        self::assertSame(0, $this->rowsIn(table: DatabaseProcessedEvents::TABLE));
    }

    public function test_AFailedDeliveryWaitsInTheRetryQueueAndKeepsNothing(): void
    {
        $this->deleteDecisionQueues();
        $this->useRetryDelays(retryDelaysMilliseconds: self::LONG_RETRY);
        $this->declareDecisionQueues();
        $this->failMail();
        $this->publishApproval();

        $this->consumeUntilEmpty();

        $waiting = $this->waitingForRetry(retry: 1);

        self::assertCount(1, $waiting);
        self::assertSame(1, self::headerOf(message: $waiting[0], header: DecisionMessageHandler::RETRY_COUNT_HEADER));
        self::assertIsString(self::headerOf(message: $waiting[0], header: DecisionMessageHandler::LAST_ERROR_HEADER));
        self::assertSame(0, $this->rowsIn(table: NotificationRows::TABLE));
        self::assertSame(0, $this->rowsIn(table: DatabaseProcessedEvents::TABLE));
    }

    public function test_AfterTheLastRetryTheDecisionIsDeadLettered(): void
    {
        $this->failMail();
        $this->publishApproval();
        $this->consumeUntilEmpty();
        usleep(self::RETRY_WAIT_MICROSECONDS);

        $this->consumeUntilEmpty();

        $deadLetters = $this->deadLetters();

        self::assertCount(1, $deadLetters);
        self::assertSame(
            DecisionMessageHandler::RETRIES_EXHAUSTED,
            self::headerOf(message: $deadLetters[0], header: DecisionMessageHandler::DEATH_REASON_HEADER),
        );
        self::assertSame(
            1,
            self::headerOf(message: $deadLetters[0], header: DecisionMessageHandler::RETRY_COUNT_HEADER),
        );
    }

    public function test_ARetryThatSucceedsDeliversTheDecision(): void
    {
        $this->publishApproval(headers: [DecisionMessageHandler::RETRY_COUNT_HEADER => 1]);

        $this->consumeUntilEmpty();

        self::assertSame(1, $this->rowsIn(table: NotificationRows::TABLE));
    }

    public function test_StopsCleanlyOnSigterm(): void
    {
        Signals::resolveAvailabilityUsing(static fn(): bool => true);
        pcntl_async_signals(true);
        $sendStopSignal = static function (): void {
            $processId = getmypid();
            \assert(\is_int($processId));
            posix_kill($processId, SIGTERM);
        };
        pcntl_signal(SIGALRM, $sendStopSignal);
        pcntl_alarm(1);

        try {
            $exitCode = $this->command(command: 'notification:consume')
                ->expectsOutputToContain('Notification consumer stopped.')
                ->run();
        } finally {
            pcntl_signal(SIGALRM, SIG_DFL);
        }

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    private function consumeUntilEmpty(): int
    {
        return $this->command(command: 'notification:consume', parameters: ['--stop-when-empty' => true])
            ->run();
    }

    private function failMail(): void
    {
        $config = $this->app->make(Config::class);
        $config->set('mail.default', 'smtp');
        $config->set('mail.mailers.smtp.host', '127.0.0.1');
        $config->set('mail.mailers.smtp.port', self::UNUSED_PORT);
    }

    private function rowsIn(string $table): int
    {
        return $this->app->make(ConnectionInterface::class)
            ->table($table)
            ->count();
    }
}
