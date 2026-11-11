<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Shared\Messaging\Outbox;

use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Log\Context\Repository as Context;
use Infrastructure\Shared\Messaging\Outbox\OutboxDomainEventPublisher;
use Infrastructure\Shared\Messaging\Outbox\OutboxRelay;
use Infrastructure\Shared\Messaging\RabbitMq\RabbitMqOutboxPublisher;
use Infrastructure\Shared\Messaging\RabbitMqConnectionFactory;
use Infrastructure\Shared\Persistence\BinaryUuid;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;
use Tests\Support\LaravelTestCase;
use Tests\Support\Shared\Messaging\RabbitMqTestQueue;
use Tests\Support\Shared\Messaging\SampleEventMessages;
use Tests\Support\Shared\Messaging\UsesTheOutbox;
use Tests\Support\Shared\Port\FrozenClock;
use Tests\Support\Shared\Port\SequentialIdentifierGenerator;
use Throwable;

/**
 * Commits for real (truncation, not a wrapping transaction), so a second connection sees and locks the rows.
 * It only writes the outbox, and empties it when done.
 */
final class OutboxRelayTest extends LaravelTestCase
{
    use DatabaseTruncation;
    use UsesTheOutbox;

    private const string PUBLISHED_AT = '2026-11-10 12:00:00';

    private const string TRACEPARENT = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

    private const string PEER_CONNECTION = 'outbox_relay_peer';

    private const int UNUSED_PORT = 1;

    private const int CONFIRM_TIMEOUT_SECONDS = 5;

    private const int BATCH_SIZE = 100;

    /** @var list<RabbitMqTestQueue> */
    private array $queues = [];

    protected function tearDown(): void
    {
        foreach ($this->queues as $queue) {
            $queue->close();
        }

        $this->app->make(ConnectionInterface::class)
            ->table(OutboxDomainEventPublisher::TABLE)
            ->truncate();
        $this->app->make(DatabaseManager::class)
            ->purge(self::PEER_CONNECTION);
        parent::tearDown();
    }

    public function test_PublishesThePendingMessagesAndMarksThem(): void
    {
        $queue = $this->boundQueue();
        $this->app->make(Context::class)
            ->addHidden(OutboxDomainEventPublisher::TRACEPARENT, self::TRACEPARENT);
        $this->storeSampleEvents(subjects: ['first', 'second']);

        $publishedMessages = $this->relay()
            ->relayBatch();

        $received = $queue->receivedMessages();
        $first = $received[0];
        $headers = $first->get('application_headers');

        self::assertSame(2, $publishedMessages);
        self::assertSame([], $this->pendingOutboxIds());
        self::assertSame([self::PUBLISHED_AT, self::PUBLISHED_AT], $this->outboxColumn(column: 'published_at'));
        self::assertCount(2, $received);
        self::assertSame(SampleEventMessages::ROUTING_KEY, $first->getRoutingKey());
        self::assertSame(SequentialIdentifierGenerator::nth(sequence: 1), $first->get('message_id'));
        self::assertSame(SampleEventMessages::ROUTING_KEY, $first->get('type'));
        self::assertSame('application/cloudevents+json', $first->get('content_type'));
        self::assertSame(AMQPMessage::DELIVERY_MODE_PERSISTENT, $first->get('delivery_mode'));
        self::assertInstanceOf(AMQPTable::class, $headers);
        self::assertSame([OutboxDomainEventPublisher::TRACEPARENT => self::TRACEPARENT], $headers->getNativeData());
        self::assertSame($this->outboxColumn(column: 'payload')[0], $first->getBody());
    }

    public function test_AnEmptyOutboxPublishesNothing(): void
    {
        $publishedMessages = $this->relay()
            ->relayBatch();

        self::assertSame(0, $publishedMessages);
    }

    public function test_PublishesAtMostOneBatch(): void
    {
        $queue = $this->boundQueue();
        $this->storeSampleEvents(subjects: ['first', 'second', 'third']);

        $publishedMessages = $this->relay(batchSize: 2)
            ->relayBatch();

        self::assertSame(2, $publishedMessages);
        self::assertSame([SequentialIdentifierGenerator::nth(sequence: 3)], $this->pendingOutboxIds());
    }

    public function test_SkipsTheRowsAnotherRelayIsPublishing(): void
    {
        $queue = $this->boundQueue();
        $this->storeSampleEvents(subjects: ['locked by the other relay', 'free']);
        $peer = $this->peerConnection();
        $peer->beginTransaction();
        $lockedIdBytes = BinaryUuid::toBytes(uuid: SequentialIdentifierGenerator::nth(sequence: 1));
        $peer->table(OutboxDomainEventPublisher::TABLE)
            ->where('id', $lockedIdBytes)
            ->lockForUpdate()
            ->get();

        $publishedWhileLocked = $this->relay()
            ->relayBatch();
        $receivedWhileLocked = $queue->receivedMessages();
        $peer->rollBack();
        $publishedAfterRelease = $this->relay()
            ->relayBatch();

        self::assertSame(1, $publishedWhileLocked);
        self::assertCount(1, $receivedWhileLocked);
        self::assertSame(SequentialIdentifierGenerator::nth(sequence: 2), $receivedWhileLocked[0]->get('message_id'));
        self::assertSame(1, $publishedAfterRelease);
        self::assertSame([], $this->pendingOutboxIds());
    }

    public function test_MessagesStayPendingWhileTheBrokerIsDownAndLeaveWhenItIsBack(): void
    {
        $queue = $this->boundQueue();
        $this->storeSampleEvents(subjects: ['waits for the broker']);
        $unreachableBroker = $this->rabbitMqOnPort(port: self::UNUSED_PORT);

        try {
            $this->relay(connections: $unreachableBroker)
                ->relayBatch();
            $hasFailed = false;
        } catch (Throwable) {
            $hasFailed = true;
        }

        $pendingWhileDown = $this->pendingOutboxIds();
        $publishedWhenBack = $this->relay()
            ->relayBatch();

        self::assertTrue($hasFailed);
        self::assertSame([SequentialIdentifierGenerator::nth(sequence: 1)], $pendingWhileDown);
        self::assertSame(1, $publishedWhenBack);
        self::assertCount(1, $queue->receivedMessages());
    }

    public function test_AMessageTheBrokerRefusesStaysPending(): void
    {
        $queue = $this->boundQueue(arguments: ['x-max-length' => 0, 'x-overflow' => 'reject-publish']);
        $this->storeSampleEvents(subjects: ['refused']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('The broker refused 1 of 1 messages.');

        try {
            $this->relay()
                ->relayBatch();
        } finally {
            self::assertSame([SequentialIdentifierGenerator::nth(sequence: 1)], $this->pendingOutboxIds());
        }
    }

    public function test_KeepsTheChannelOpenAcrossBatches(): void
    {
        $queue = $this->boundQueue();
        $publisher = $this->rabbitMqPublisher();
        $this->storeSampleEvents(subjects: ['first']);
        $this->relay(publisher: $publisher)
            ->relayBatch();
        $this->storeSampleEvents(subjects: ['on the same channel'], firstSequence: 2);

        $publishedOnTheSameChannel = $this->relay(publisher: $publisher)
            ->relayBatch();

        self::assertSame(1, $publishedOnTheSameChannel);
        self::assertCount(2, $queue->receivedMessages());
    }

    public function test_ReconnectsAfterABrokenChannel(): void
    {
        $queue = $this->boundQueue();
        $publisher = $this->rabbitMqPublisher();
        $this->storeSampleEvents(subjects: ['first']);
        $this->relay(publisher: $publisher)
            ->relayBatch();
        $publisher->disconnect();
        $this->storeSampleEvents(subjects: ['after the reconnection'], firstSequence: 2);

        $publishedAfterReconnecting = $this->relay(publisher: $publisher)
            ->relayBatch();

        self::assertSame(1, $publishedAfterReconnecting);
        self::assertCount(2, $queue->receivedMessages());
    }

    private function relay(
        int $batchSize = self::BATCH_SIZE,
        ?RabbitMqConnectionFactory $connections = null,
        ?RabbitMqOutboxPublisher $publisher = null,
    ): OutboxRelay {
        $publisher ??= $this->rabbitMqPublisher(connections: $connections);
        $publishedAt = new DateTimeImmutable(self::PUBLISHED_AT);

        return new OutboxRelay(
            connection: $this->app->make(ConnectionInterface::class),
            publisher: $publisher,
            clock: new FrozenClock(frozenAt: $publishedAt),
            batchSize: $batchSize,
        );
    }

    private function rabbitMqPublisher(?RabbitMqConnectionFactory $connections = null): RabbitMqOutboxPublisher
    {
        return new RabbitMqOutboxPublisher(
            connections: $connections ?? $this->app->make(RabbitMqConnectionFactory::class),
            confirmTimeoutSeconds: self::CONFIRM_TIMEOUT_SECONDS,
        );
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function boundQueue(array $arguments = []): RabbitMqTestQueue
    {
        $queue = RabbitMqTestQueue::boundTo(
            connections: $this->app->make(RabbitMqConnectionFactory::class),
            exchange: SampleEventMessages::EXCHANGE,
            arguments: $arguments,
        );
        $this->queues[] = $queue;

        return $queue;
    }

    private function peerConnection(): ConnectionInterface
    {
        $config = $this->app->make(Config::class);
        $config->set('database.connections.' . self::PEER_CONNECTION, $config->array('database.connections.mysql'));

        return $this->app->make(DatabaseManager::class)
            ->connection(self::PEER_CONNECTION);
    }

}
