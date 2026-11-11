<?php

declare(strict_types=1);

namespace Tests\Support\Shared\Messaging;

use Infrastructure\Shared\Messaging\RabbitMq\RabbitMqTopology;
use Infrastructure\Shared\Messaging\RabbitMqConnectionFactory;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * A server-named, exclusive queue: the broker deletes it when the test closes the connection.
 */
final class RabbitMqTestQueue
{
    private const string EVERY_ROUTING_KEY = '#';

    private function __construct(
        private readonly AbstractConnection $connection,
        private readonly AMQPChannel $channel,
        public readonly string $name,
    ) {}

    /**
     * @param array<string, mixed> $arguments
     */
    public static function boundTo(RabbitMqConnectionFactory $connections, string $exchange, array $arguments = []): self
    {
        $connection = $connections->create();
        $channel = $connection->channel();
        RabbitMqTopology::declareEventExchange(channel: $channel, exchange: $exchange);
        $declared = $channel->queue_declare(
            '',
            false,
            false,
            true,
            true,
            false,
            new AMQPTable($arguments),
        );
        \assert(\is_array($declared) && \is_string($declared[0]));
        $channel->queue_bind($declared[0], $exchange, self::EVERY_ROUTING_KEY);

        return new self(connection: $connection, channel: $channel, name: $declared[0]);
    }

    /**
     * @return list<AMQPMessage>
     */
    public function receivedMessages(): array
    {
        $messages = [];

        while (($message = $this->channel->basic_get($this->name, true)) instanceof AMQPMessage) {
            $messages[] = $message;
        }

        return $messages;
    }

    public function close(): void
    {
        $this->connection->close();
    }
}
