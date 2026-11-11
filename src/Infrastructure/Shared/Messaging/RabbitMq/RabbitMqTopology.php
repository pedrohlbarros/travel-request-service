<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Messaging\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exchange\AMQPExchangeType;

/**
 * Declarations are idempotent while the arguments match, so every side declares with these same arguments.
 */
final readonly class RabbitMqTopology
{
    private const bool IS_PASSIVE = false;

    private const bool IS_DURABLE = true;

    private const bool IS_AUTO_DELETE = false;

    public static function declareEventExchange(AMQPChannel $channel, string $exchange): void
    {
        $channel->exchange_declare(
            $exchange,
            AMQPExchangeType::TOPIC,
            self::IS_PASSIVE,
            self::IS_DURABLE,
            self::IS_AUTO_DELETE,
        );
    }
}
