<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Infrastructure\Shared\Config\TypedConfig;
use Infrastructure\Shared\Messaging\RabbitMq\RabbitMqTopology;
use Infrastructure\Shared\Messaging\RabbitMqConnectionFactory;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * An inspection queue bound to everything on an exchange, for looking at what really leaves the outbox.
 */
final class TapMessagesCommand extends Command
{
    public const string QUEUE_PREFIX = 'inspection.';

    private const string EVERY_ROUTING_KEY = '#';

    private const int JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    protected $signature = 'messaging:tap
        {exchange? : Exchange to tap (defaults to the travel request events)}
        {--limit=20 : Most messages to print (each one is taken off the queue)}';

    protected $description = 'Bind an inspection queue to an exchange and print the messages it received';

    public function handle(RabbitMqConnectionFactory $connections, Config $config): int
    {
        $exchangeArgument = $this->argument('exchange');
        $exchange = \is_string($exchangeArgument)
            ? $exchangeArgument
            : TypedConfig::nonEmptyString(config: $config, key: 'messaging.tap.exchange');
        $queue = self::QUEUE_PREFIX . $exchange;
        $connection = $connections->create();
        $channel = $connection->channel();
        $this->declareQueue(
            channel: $channel,
            exchange: $exchange,
            queue: $queue,
            maxLength: TypedConfig::positiveInt(config: $config, key: 'messaging.tap.max_length'),
        );

        $printedMessages = $this->printMessages(channel: $channel, queue: $queue);
        $connection->close();

        if ($printedMessages === 0) {
            $this->components->info(\sprintf(
                'No messages in %s yet; it now receives everything published to %s.',
                $queue,
                $exchange,
            ));
        }

        return self::SUCCESS;
    }

    private function declareQueue(AMQPChannel $channel, string $exchange, string $queue, int $maxLength): void
    {
        RabbitMqTopology::declareEventExchange(channel: $channel, exchange: $exchange);
        $channel->queue_declare(
            $queue,
            false,
            true,
            false,
            false,
            false,
            new AMQPTable(['x-queue-type' => 'quorum', 'x-max-length' => $maxLength]),
        );
        $channel->queue_bind($queue, $exchange, self::EVERY_ROUTING_KEY);
    }

    private function printMessages(AMQPChannel $channel, string $queue): int
    {
        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) ? (int) $limitOption : 0;
        $printedMessages = 0;

        while ($printedMessages < $limit) {
            $message = $channel->basic_get($queue);

            if (! $message instanceof AMQPMessage) {
                break;
            }

            $this->printMessage(message: $message);
            $message->ack();
            $printedMessages++;
        }

        return $printedMessages;
    }

    private function printMessage(AMQPMessage $message): void
    {
        $headers = self::propertyOf(message: $message, name: 'application_headers');
        $payload = json_decode($message->getBody(), true);
        $summary = [
            'routing_key' => $message->getRoutingKey(),
            'properties' => [
                'message_id' => self::propertyOf(message: $message, name: 'message_id'),
                'type' => self::propertyOf(message: $message, name: 'type'),
                'content_type' => self::propertyOf(message: $message, name: 'content_type'),
                'delivery_mode' => self::propertyOf(message: $message, name: 'delivery_mode'),
            ],
            'headers' => $headers instanceof AMQPTable ? $headers->getNativeData() : [],
            'payload' => $payload ?? $message->getBody(),
        ];

        $this->line((string) json_encode($summary, self::JSON_FLAGS));
    }

    private static function propertyOf(AMQPMessage $message, string $name): mixed
    {
        return $message->has($name) ? $message->get($name) : null;
    }
}
