<?php

declare(strict_types=1);

namespace Tests\Support\Notification\Fixture;

use Illuminate\Contracts\Config\Repository as Config;
use Infrastructure\Notification\Messaging\DecisionConsumerSettings;
use Infrastructure\Notification\Messaging\DecisionMessageHandler;
use Infrastructure\Notification\Messaging\DecisionTopology;
use Infrastructure\Shared\Messaging\RabbitMqConnectionFactory;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * The suite's own notification queues (named in phpunit.xml), created fresh and deleted after each test.
 */
trait UsesDecisionQueues
{
    private ?AbstractConnection $queueConnection = null;

    protected function useRetryDelays(string $retryDelaysMilliseconds): void
    {
        $this->app->make(Config::class)
            ->set('notification.consumer.retry_delays_ms', $retryDelaysMilliseconds);
    }

    protected function declareDecisionQueues(): void
    {
        $this->deleteDecisionQueues();
        DecisionTopology::declare(channel: $this->queueChannel(), settings: $this->decisionSettings());
    }

    protected function deleteDecisionQueues(): void
    {
        $settings = $this->decisionSettings();
        $channel = $this->queueChannel();
        $channel->queue_delete($settings->queue);
        $channel->queue_delete($settings->deadLetterQueue());

        for ($retry = 1; $retry <= $settings->maxRetries(); $retry++) {
            $channel->queue_delete($settings->retryQueue(retry: $retry));
        }
    }

    protected function closeDecisionQueues(): void
    {
        $this->deleteDecisionQueues();
        $this->queueConnection?->close();
        $this->queueConnection = null;
    }

    /**
     * @param array<string, int|string> $headers
     */
    protected function publishDecision(string $body, array $headers = []): void
    {
        $message = new AMQPMessage(
            $body,
            ['content_type' => 'application/cloudevents+json', 'application_headers' => new AMQPTable($headers)],
        );
        $exchange = $this->decisionSettings()->exchange;
        $this->queueChannel()
            ->basic_publish($message, $exchange, DecisionMessages::APPROVED);
    }

    /**
     * @param array<string, int|string> $headers
     */
    protected function parkInDeadLetterQueue(string $body, array $headers): void
    {
        $message = new AMQPMessage(
            $body,
            ['type' => DecisionMessages::APPROVED, 'application_headers' => new AMQPTable($headers)],
        );
        $deadLetterQueue = $this->decisionSettings()
            ->deadLetterQueue();
        $this->queueChannel()
            ->basic_publish($message, '', $deadLetterQueue);
    }

    /**
     * @param array<string, mixed> $dataOverrides
     * @param array<string, int|string> $headers
     */
    protected function publishApproval(array $dataOverrides = [], array $headers = []): void
    {
        $event = DecisionMessages::event(dataOverrides: $dataOverrides);
        $this->publishDecision(body: DecisionMessages::body(event: $event), headers: $headers);
    }

    /**
     * @return list<AMQPMessage>
     */
    protected function deadLetters(): array
    {
        $deadLetterQueue = $this->decisionSettings()
            ->deadLetterQueue();

        return $this->messagesIn(queue: $deadLetterQueue);
    }

    /**
     * @return list<AMQPMessage>
     */
    protected function waitingForRetry(int $retry): array
    {
        $retryQueue = $this->decisionSettings()
            ->retryQueue(retry: $retry);

        return $this->messagesIn(queue: $retryQueue);
    }

    protected static function headerOf(AMQPMessage $message, string $header): mixed
    {
        return self::headersOf(message: $message)[$header] ?? null;
    }

    /**
     * Takes every message off the queue.
     *
     * @return list<AMQPMessage>
     */
    protected function messagesIn(string $queue): array
    {
        $messages = [];

        $channel = $this->queueChannel();

        while (($message = $channel->basic_get($queue, true)) instanceof AMQPMessage) {
            $messages[] = $message;
        }

        return $messages;
    }

    protected function decisionSettings(): DecisionConsumerSettings
    {
        return $this->app->make(DecisionConsumerSettings::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function headersOf(AMQPMessage $message): array
    {
        return DecisionMessageHandler::headersOf(message: $message);
    }

    private function queueChannel(): AMQPChannel
    {
        $this->queueConnection ??= $this->app->make(RabbitMqConnectionFactory::class)
            ->create();

        return $this->queueConnection->channel(1);
    }
}
