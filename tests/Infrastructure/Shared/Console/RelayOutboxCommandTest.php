<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Shared\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Signals;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Sleep;
use Infrastructure\Shared\Health\MySqlReplicaStatus;
use Infrastructure\Shared\Messaging\RabbitMqConnectionFactory;
use Tests\Support\LaravelTestCase;
use Tests\Support\Shared\Messaging\RabbitMqTestQueue;
use Tests\Support\Shared\Messaging\SampleEventMessages;
use Tests\Support\Shared\Messaging\UsesTheOutbox;
use Tests\Support\Shared\Observability\CapturesTelemetry;

final class RelayOutboxCommandTest extends LaravelTestCase
{
    use CapturesTelemetry;
    use RefreshDatabase;
    use UsesTheOutbox;

    private const int UNUSED_PORT = 1;

    private RabbitMqTestQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queue = RabbitMqTestQueue::boundTo(
            connections: $this->app->make(RabbitMqConnectionFactory::class),
            exchange: SampleEventMessages::EXCHANGE,
        );
    }

    protected function tearDown(): void
    {
        $this->queue->close();
        $this->releaseTelemetry();
        parent::tearDown();
    }

    public function test_OnceRelaysOneBatchAndExits(): void
    {
        $this->storeSampleEvents(subjects: ['relayed']);

        $exitCode = $this->command(command: 'outbox:relay', parameters: ['--once' => true])
            ->expectsOutputToContain('Published 1 outbox messages.')
            ->run();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(0, $this->pendingCount());
        self::assertCount(1, $this->queue->receivedMessages());
    }

    public function test_OnceFailsAndKeepsTheBatchWhenTheBrokerIsDown(): void
    {
        $this->storeSampleEvents(subjects: ['relayed']);
        $this->useBrokerOnPort(port: self::UNUSED_PORT);

        $exitCode = $this->command(command: 'outbox:relay', parameters: ['--once' => true])
            ->expectsOutputToContain('Batch failed, messages stay pending')
            ->run();

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(1, $this->pendingCount());
    }

    public function test_ReportsHowManyMessagesArePending(): void
    {
        $this->storeSampleEvents(subjects: ['first', 'second']);
        $this->useBrokerOnPort(port: self::UNUSED_PORT);
        $this->captureTelemetry();

        $this->command(command: 'outbox:relay', parameters: ['--once' => true])
            ->run();

        self::assertSame(2, $this->capturedValue(metricName: 'outbox.pending'));
        self::assertIsInt($this->capturedValue(metricName: 'mysql.replica.lag'));
        self::assertSame(1, $this->capturedValue(metricName: 'mysql.replica.running'));
    }

    public function test_ReportsNoReplicaLagAndAStoppedReplicaWhileItIsUnreachable(): void
    {
        $this->app->make(Config::class)
            ->set('database.connections.' . MySqlReplicaStatus::CONNECTION . '.host', 'no-such-replica.invalid');
        $this->useBrokerOnPort(port: self::UNUSED_PORT);
        $this->captureTelemetry();

        $this->command(command: 'outbox:relay', parameters: ['--once' => true])
            ->run();

        self::assertNull($this->capturedValue(metricName: 'mysql.replica.lag'));
        self::assertSame(0, $this->capturedValue(metricName: 'mysql.replica.running'));
    }

    public function test_KeepsPollingUntilAStopSignalAndFinishesTheBatchFirst(): void
    {
        $this->storeSampleEvents(subjects: ['relayed']);
        self::stopOnTheFirstSleep();

        $exitCode = $this->command(command: 'outbox:relay')
            ->expectsOutputToContain('Outbox relay stopped.')
            ->run();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(0, $this->pendingCount());
        Sleep::assertSleptTimes(1);
    }

    public function test_BacksOffWhileTheBrokerIsDown(): void
    {
        $this->storeSampleEvents(subjects: ['relayed']);
        $this->useBrokerOnPort(port: self::UNUSED_PORT);
        self::stopOnTheFirstSleep();

        $this->command(command: 'outbox:relay')
            ->run();

        self::assertSame(1, $this->pendingCount());
        $backedOffSleep = Sleep::for(400)
            ->milliseconds();
        Sleep::assertSequence([$backedOffSleep]);
    }

    private function useBrokerOnPort(int $port): void
    {
        $unreachableBroker = $this->rabbitMqOnPort(port: $port);
        $this->app->instance(RabbitMqConnectionFactory::class, $unreachableBroker);
    }

    private static function stopOnTheFirstSleep(): void
    {
        Signals::resolveAvailabilityUsing(static fn(): bool => true);
        Sleep::fake();
        Sleep::whenFakingSleep(static function (): void {
            $processId = getmypid();
            \assert(\is_int($processId));
            posix_kill($processId, SIGTERM);
        });
    }

    private function pendingCount(): int
    {
        return \count($this->pendingOutboxIds());
    }
}
