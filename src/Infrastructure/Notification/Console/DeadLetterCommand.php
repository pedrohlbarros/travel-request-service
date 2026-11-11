<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Console;

use Illuminate\Console\Command;
use Infrastructure\Notification\Messaging\DecisionConsumerSettings;
use Infrastructure\Notification\Messaging\DecisionMessageHandler;
use Infrastructure\Notification\Messaging\DecisionTopology;
use Infrastructure\Shared\Messaging\RabbitMqConnectionFactory;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Lists the dead-lettered decisions without taking them off the queue, or sends them back for a fresh try.
 */
final class DeadLetterCommand extends Command
{
    private const int JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    private const string DEFAULT_EXCHANGE = '';

    protected $signature = 'notification:dlq
        {--replay : Send every dead-lettered message back to the notification queue}
        {--limit=50 : Most messages to print or replay}';

    protected $description = 'Inspect or replay the notification dead letter queue';

    public function handle(RabbitMqConnectionFactory $connections, DecisionConsumerSettings $settings): int
    {
        $connection = $connections->create();
        $channel = $connection->channel();
        DecisionTopology::declare(channel: $channel, settings: $settings);
        $channel->confirm_select();
        $messages = $this->takeMessages(channel: $channel, queue: $settings->deadLetterQueue());

        foreach ($messages as $message) {
            $this->settle(channel: $channel, message: $message, settings: $settings);
        }

        $channel->wait_for_pending_acks_returns($settings->confirmTimeoutSeconds);
        $connection->close();

        $verb = $this->option('replay') === true ? 'Replayed' : 'Listed';
        $settledMessages = \count($messages);
        $this->components->info(\sprintf('%s %d dead-lettered messages.', $verb, $settledMessages));

        return self::SUCCESS;
    }

    /**
     * @return list<AMQPMessage>
     */
    private function takeMessages(AMQPChannel $channel, string $queue): array
    {
        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) ? (int) $limitOption : 0;
        $messages = [];

        while (\count($messages) < $limit) {
            $message = $channel->basic_get($queue);

            if (! $message instanceof AMQPMessage) {
                break;
            }

            $messages[] = $message;
        }

        return $messages;
    }

    private function settle(AMQPChannel $channel, AMQPMessage $message, DecisionConsumerSettings $settings): void
    {
        if ($this->option('replay') !== true) {
            $this->printMessage(message: $message);
            $message->nack(true);

            return;
        }

        $headers = DecisionMessageHandler::headersOf(message: $message);
        unset($headers[DecisionMessageHandler::DEATH_REASON_HEADER], $headers[DecisionMessageHandler::LAST_ERROR_HEADER]);
        $properties = $message->get_properties();
        $properties['application_headers'] = new AMQPTable([...$headers, DecisionMessageHandler::RETRY_COUNT_HEADER => 0]);

        $replayed = new AMQPMessage($message->getBody(), $properties);
        $channel->basic_publish($replayed, self::DEFAULT_EXCHANGE, $settings->queue);
        $message->ack();
    }

    private function printMessage(AMQPMessage $message): void
    {
        $headers = DecisionMessageHandler::headersOf(message: $message);
        $summary = [
            'reason' => $headers[DecisionMessageHandler::DEATH_REASON_HEADER] ?? null,
            'error' => $headers[DecisionMessageHandler::LAST_ERROR_HEADER] ?? null,
            'retries' => DecisionMessageHandler::retryCountOf(message: $message),
            'type' => $message->has('type') ? $message->get('type') : null,
            'body' => json_decode($message->getBody(), true) ?? $message->getBody(),
        ];

        $this->line((string) json_encode($summary, self::JSON_FLAGS));
    }
}
