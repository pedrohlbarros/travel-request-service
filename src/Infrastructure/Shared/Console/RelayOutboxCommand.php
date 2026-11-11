<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Console;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Sleep;
use Infrastructure\Shared\Health\MySqlReplicaStatus;
use Infrastructure\Shared\Messaging\Outbox\OutboxDomainEventPublisher;
use Infrastructure\Shared\Messaging\Outbox\OutboxRelay;
use Infrastructure\Shared\Messaging\Outbox\OutboxRelaySettings;
use Infrastructure\Shared\Messaging\Outbox\PollDelay;
use Infrastructure\Shared\Observability\Telemetry;
use OpenTelemetry\API\Metrics\ObserverInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class RelayOutboxCommand extends Command
{
    protected $signature = 'outbox:relay {--once : Relay a single batch and exit}';

    protected $description = 'Publish the pending outbox messages to RabbitMQ';

    private bool $isStopping = false;

    public function handle(
        OutboxRelay $relay,
        OutboxRelaySettings $settings,
        LoggerInterface $logger,
        Telemetry $telemetry,
        ConnectionInterface $connection,
        MySqlReplicaStatus $replica,
    ): int {
        self::observePendingMessages(telemetry: $telemetry, connection: $connection);
        self::observeReplicaLag(telemetry: $telemetry, replica: $replica);
        self::observeReplicaRunning(telemetry: $telemetry, replica: $replica);

        if ($this->option('once') === true) {
            $publishedMessages = $this->relayBatch(relay: $relay, logger: $logger);
            $telemetry->flush();

            return $publishedMessages === null ? self::FAILURE : self::SUCCESS;
        }

        $this->trap([SIGTERM, SIGINT], function (): void {
            $this->isStopping = true;
        });

        $pollDelay = PollDelay::startingAt(
            minMilliseconds: $settings->minPollMilliseconds,
            maxMilliseconds: $settings->maxPollMilliseconds,
        );

        while (! $this->isStopping) {
            $publishedMessages = $this->relayBatch(relay: $relay, logger: $logger);
            $pollDelay = $publishedMessages === null
                ? $pollDelay->backedOff()
                : $pollDelay->afterBatch(publishedMessages: $publishedMessages);
            $telemetry->flush();
            Sleep::for($pollDelay->milliseconds)
                ->milliseconds();
        }

        $this->components->info('Outbox relay stopped.');

        return self::SUCCESS;
    }

    /**
     * Only the relay reports the backlog, so it is one series however many web workers run.
     */
    private static function observePendingMessages(Telemetry $telemetry, ConnectionInterface $connection): void
    {
        $telemetry->meter()
            ->createObservableGauge('outbox.pending', '{message}', 'Outbox messages not yet published.')
            ->observe(static function (ObserverInterface $observer) use ($connection): void {
                $pendingMessages = $connection->table(OutboxDomainEventPublisher::TABLE)
                    ->whereNull('published_at')
                    ->count();
                $observer->observe($pendingMessages);
            });
    }

    /**
     * The relay runs once, so the replica lag is one series; nothing is observed while the replica is down.
     */
    private static function observeReplicaLag(Telemetry $telemetry, MySqlReplicaStatus $replica): void
    {
        $telemetry->meter()
            ->createObservableGauge('mysql.replica.lag', 's', 'How far the read replica is behind the primary.')
            ->observe(static function (ObserverInterface $observer) use ($replica): void {
                $secondsBehind = rescue(static fn(): ?int => $replica->current()?->secondsBehindSource, report: false);

                if (\is_int($secondsBehind)) {
                    $observer->observe($secondsBehind);
                }
            });
    }

    /**
     * One while both replication threads run, zero when they stopped, the replica is not set up or cannot be
     * reached, so an alert can tell a stopped replica from a lagging one.
     */
    private static function observeReplicaRunning(Telemetry $telemetry, MySqlReplicaStatus $replica): void
    {
        $telemetry->meter()
            ->createObservableGauge('mysql.replica.running', null, 'Whether the read replica is replicating.')
            ->observe(static function (ObserverInterface $observer) use ($replica): void {
                $isReplicating = rescue(
                    static fn(): bool => $replica->current()?->isReplicating === true,
                    rescue: false,
                    report: false,
                );
                $observer->observe($isReplicating ? 1 : 0);
            });
    }

    /**
     * @return ?int how many messages were published, or null when the batch failed and stays pending
     */
    private function relayBatch(OutboxRelay $relay, LoggerInterface $logger): ?int
    {
        try {
            $publishedMessages = $relay->relayBatch();
        } catch (Throwable $exception) {
            $logger->warning('Outbox relay batch failed; its messages stay pending.', ['exception' => $exception]);
            $failure = $exception->getMessage();
            $this->components->warn(\sprintf('Batch failed, messages stay pending: %s', $failure));

            return null;
        }

        if ($publishedMessages > 0) {
            $this->components->info(\sprintf('Published %d outbox messages.', $publishedMessages));
        }

        return $publishedMessages;
    }
}
