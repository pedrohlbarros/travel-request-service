<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Messaging;

use Infrastructure\Shared\Messaging\RabbitMq\RabbitMqTopology;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * The consumer owns its queues: the main one bound to the decisions, one wait queue per retry delay that
 * dead-letters back to the main queue, and the dead letter queue for what cannot be delivered.
 */
final readonly class DecisionTopology
{
    private const string DEFAULT_EXCHANGE = '';

    private const bool IS_PASSIVE = false;

    private const bool IS_DURABLE = true;

    private const bool IS_EXCLUSIVE = false;

    private const bool IS_AUTO_DELETE = false;

    public static function declare(AMQPChannel $channel, DecisionConsumerSettings $settings): void
    {
        RabbitMqTopology::declareEventExchange(channel: $channel, exchange: $settings->exchange);
        self::declareQuorumQueue(channel: $channel, queue: $settings->queue, arguments: []);
        $channel->queue_bind($settings->queue, $settings->exchange, $settings->bindingKey);
        self::declareQuorumQueue(channel: $channel, queue: $settings->deadLetterQueue(), arguments: []);

        for ($retry = 1; $retry <= $settings->maxRetries(); $retry++) {
            self::declareQuorumQueue(
                channel: $channel,
                queue: $settings->retryQueue(retry: $retry),
                arguments: [
                    'x-message-ttl' => $settings->retryDelay(retry: $retry),
                    'x-dead-letter-exchange' => self::DEFAULT_EXCHANGE,
                    'x-dead-letter-routing-key' => $settings->queue,
                ],
            );
        }
    }

    /**
     * @param array<string, int|string> $arguments
     */
    private static function declareQuorumQueue(AMQPChannel $channel, string $queue, array $arguments): void
    {
        $channel->queue_declare(
            $queue,
            self::IS_PASSIVE,
            self::IS_DURABLE,
            self::IS_EXCLUSIVE,
            self::IS_AUTO_DELETE,
            false,
            new AMQPTable(['x-queue-type' => 'quorum', ...$arguments]),
        );
    }
}
