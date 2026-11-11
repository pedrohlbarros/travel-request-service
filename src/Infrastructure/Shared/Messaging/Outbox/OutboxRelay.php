<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Messaging\Outbox;

use Application\Shared\Port\Clock;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Infrastructure\Shared\Messaging\RabbitMq\RabbitMqOutboxPublisher;
use Infrastructure\Shared\Observability\Telemetry;
use Throwable;

/**
 * One batch per call. Rows stay locked until the broker confirms them, and other relays skip what is locked,
 * so relays run side by side without publishing the same row twice.
 */
final readonly class OutboxRelay
{
    private const string SKIP_LOCKED = 'for update skip locked';

    private const string TIMEZONE = 'UTC';

    public function __construct(
        private ConnectionInterface $connection,
        private RabbitMqOutboxPublisher $publisher,
        private Clock $clock,
        private Telemetry $telemetry,
        private int $batchSize,
    ) {}

    /**
     * @return int how many messages were published
     */
    public function relayBatch(): int
    {
        return $this->connection->transaction(function (): int {
            $rows = $this->connection->table(OutboxDomainEventPublisher::TABLE)
                ->whereNull('published_at')
                ->orderBy('id')
                ->limit($this->batchSize)
                ->lock(self::SKIP_LOCKED)
                ->get(['id', 'exchange', 'routing_key', 'payload', 'headers']);

            $messages = [];
            foreach ($rows as $row) {
                $messages[] = PendingOutboxMessage::fromRow(row: $row);
            }

            if ($messages === []) {
                return 0;
            }

            $batch = TracedOutboxBatch::start(telemetry: $this->telemetry, messages: $messages);

            try {
                $this->publisher->publish(messages: $batch->messages);
            } catch (Throwable $failure) {
                $batch->fail(failure: $failure);

                throw $failure;
            }

            $this->markPublished(messages: $messages);
            $batch->succeed();
            $this->countPublished(messages: $messages);

            return \count($messages);
        });
    }

    /**
     * @param list<PendingOutboxMessage> $messages
     */
    private function countPublished(array $messages): void
    {
        $published = $this->telemetry->meter()
            ->createCounter('outbox.published', '{message}', 'Outbox messages the broker confirmed.');

        foreach ($messages as $message) {
            $published->add(1, ['messaging.rabbitmq.destination.routing_key' => $message->routingKey]);
        }
    }

    /**
     * @param list<PendingOutboxMessage> $messages
     */
    private function markPublished(array $messages): void
    {
        $publishedAt = $this->clock->now()
            ->setTimezone(new DateTimeZone(self::TIMEZONE));
        $ids = array_map(static fn(PendingOutboxMessage $message): string => $message->idBytes, $messages);

        $this->connection->table(OutboxDomainEventPublisher::TABLE)
            ->whereIn('id', $ids)
            ->update(['published_at' => $publishedAt->format(OutboxDomainEventPublisher::DATETIME_FORMAT)]);
    }
}
