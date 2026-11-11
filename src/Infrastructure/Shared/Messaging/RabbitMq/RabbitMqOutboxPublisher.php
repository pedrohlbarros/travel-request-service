<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Messaging\RabbitMq;

use Infrastructure\Shared\Messaging\Outbox\CloudEvent;
use Infrastructure\Shared\Messaging\Outbox\PendingOutboxMessage;
use Infrastructure\Shared\Messaging\RabbitMqConnectionFactory;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;
use Throwable;

/**
 * Keeps one confirming channel for the relay's whole life and drops it on any failure, so the next batch
 * reconnects. Only the long-running relay uses it, never a web worker.
 */
final class RabbitMqOutboxPublisher
{
    private ?AbstractConnection $connection = null;

    private ?AMQPChannel $channel = null;

    private int $nackedMessages = 0;

    public function __construct(
        private readonly RabbitMqConnectionFactory $connections,
        private readonly int $confirmTimeoutSeconds,
    ) {}

    /**
     * Returns once the broker has confirmed every message.
     *
     * @param list<PendingOutboxMessage> $messages
     */
    public function publish(array $messages): void
    {
        try {
            $channel = $this->channel();
            $this->nackedMessages = 0;

            foreach ($messages as $message) {
                RabbitMqTopology::declareEventExchange(channel: $channel, exchange: $message->exchange);
                $amqpMessage = self::amqpMessageOf(message: $message);
                $channel->basic_publish($amqpMessage, $message->exchange, $message->routingKey);
            }

            $channel->wait_for_pending_acks_returns($this->confirmTimeoutSeconds);
        } catch (Throwable $exception) {
            $this->disconnect();

            throw $exception;
        }

        $refusedMessages = $this->refusedMessages();

        if ($refusedMessages > 0) {
            $sentMessages = \count($messages);

            throw new RuntimeException(\sprintf(
                'The broker refused %d of %d messages.',
                $refusedMessages,
                $sentMessages,
            ));
        }
    }

    public function disconnect(): void
    {
        $connection = $this->connection;
        $this->connection = null;
        $this->channel = null;

        rescue(static fn() => $connection?->close(), report: false);
    }

    /**
     * Read through a method: the nack handler raises the count while waiting for the confirms.
     */
    private function refusedMessages(): int
    {
        return $this->nackedMessages;
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel !== null) {
            return $this->channel;
        }

        $this->connection = $this->connections->create();
        $channel = $this->connection->channel();
        $channel->confirm_select();
        $channel->set_nack_handler(function (): void {
            $this->nackedMessages++;
        });
        $this->channel = $channel;

        return $channel;
    }

    private static function amqpMessageOf(PendingOutboxMessage $message): AMQPMessage
    {
        return new AMQPMessage(
            $message->payload,
            [
                'content_type' => CloudEvent::CONTENT_TYPE,
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $message->eventId,
                'type' => $message->routingKey,
                'application_headers' => new AMQPTable($message->headers),
            ],
        );
    }
}
