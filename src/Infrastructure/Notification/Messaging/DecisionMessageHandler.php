<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Messaging;

use Application\Notification\UseCase\NotifyRequesterOfDecisionUseCase;
use Domain\Notification\Exception\InvalidDecision;
use Domain\Shared\Exception\InvalidIdentifier;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Every message is acknowledged only after it is settled: delivered, parked in a wait queue for a retry, or
 * dead-lettered. A message that cannot be read is dead-lettered at once, since retrying cannot fix it.
 */
final readonly class DecisionMessageHandler
{
    public const string RETRY_COUNT_HEADER = 'x-retry-count';

    public const string DEATH_REASON_HEADER = 'x-death-reason';

    public const string LAST_ERROR_HEADER = 'x-last-error';

    public const string UNREADABLE = 'unreadable';

    public const string RETRIES_EXHAUSTED = 'retries_exhausted';

    private const string DEFAULT_EXCHANGE = '';

    public function __construct(
        private NotifyRequesterOfDecisionUseCase $notifyRequester,
        private DecisionMessageParser $parser,
        private DecisionConsumerSettings $settings,
        private LoggerInterface $logger,
    ) {}

    public function handle(AMQPMessage $message): void
    {
        $channel = $message->getChannel();
        \assert($channel instanceof AMQPChannel);

        try {
            $input = $this->parser->parse(body: $message->getBody());
            $this->notifyRequester->execute(input: $input);
        } catch (UnreadableDecisionMessage|InvalidDecision|InvalidIdentifier $unreadable) {
            $this->deadLetter(
                channel: $channel,
                message: $message,
                reason: self::UNREADABLE,
                failure: $unreadable,
            );
        } catch (Throwable $failure) {
            $this->retryOrDeadLetter(channel: $channel, message: $message, failure: $failure);
        }

        $message->ack();
    }

    public static function retryCountOf(AMQPMessage $message): int
    {
        $headers = self::headersOf(message: $message);
        $retryCount = $headers[self::RETRY_COUNT_HEADER] ?? 0;

        return \is_int($retryCount) ? $retryCount : 0;
    }

    /**
     * @return array<string, mixed>
     */
    public static function headersOf(AMQPMessage $message): array
    {
        if (! $message->has('application_headers')) {
            return [];
        }

        $headers = $message->get('application_headers');

        /** @var array<string, mixed> */
        return $headers instanceof AMQPTable ? $headers->getNativeData() : [];
    }

    private function retryOrDeadLetter(AMQPChannel $channel, AMQPMessage $message, Throwable $failure): void
    {
        $retry = self::retryCountOf(message: $message) + 1;

        if ($retry > $this->settings->maxRetries()) {
            $this->deadLetter(
                channel: $channel,
                message: $message,
                reason: self::RETRIES_EXHAUSTED,
                failure: $failure,
            );

            return;
        }

        $this->logger->warning(
            'Decision delivery failed; it will be retried.',
            ['retry' => $retry, 'exception' => $failure],
        );
        $this->republish(
            channel: $channel,
            message: $message,
            queue: $this->settings->retryQueue(retry: $retry),
            headers: [self::RETRY_COUNT_HEADER => $retry, self::LAST_ERROR_HEADER => $failure->getMessage()],
        );
    }

    private function deadLetter(AMQPChannel $channel, AMQPMessage $message, string $reason, Throwable $failure): void
    {
        $this->logger->error('Decision message dead-lettered.', ['reason' => $reason, 'exception' => $failure]);
        $this->republish(
            channel: $channel,
            message: $message,
            queue: $this->settings->deadLetterQueue(),
            headers: [self::DEATH_REASON_HEADER => $reason, self::LAST_ERROR_HEADER => $failure->getMessage()],
        );
    }

    /**
     * @param array<string, int|string> $headers
     */
    private function republish(AMQPChannel $channel, AMQPMessage $message, string $queue, array $headers): void
    {
        $properties = $message->get_properties();
        $properties['application_headers'] = new AMQPTable([...self::headersOf(message: $message), ...$headers]);
        $copy = new AMQPMessage($message->getBody(), $properties);

        $channel->basic_publish($copy, self::DEFAULT_EXCHANGE, $queue);
        $channel->wait_for_pending_acks_returns($this->settings->confirmTimeoutSeconds);
    }
}
