<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Messaging;

use Application\Notification\Output\NotificationDelivery;
use Application\Notification\UseCase\NotifyRequesterOfDecisionUseCase;
use Application\Shared\Port\Clock;
use DateTimeImmutable;
use Domain\Notification\Exception\InvalidDecision;
use Domain\Shared\Exception\InvalidIdentifier;
use Illuminate\Log\Context\Repository as Context;
use Infrastructure\Shared\Observability\Telemetry;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
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

    public const string DELIVERED = 'delivered';

    public const string DUPLICATE = 'duplicate';

    public const string RETRIED = 'retried';

    public const string DEAD_LETTERED = 'dead_lettered';

    public const string REQUEST_ID_HEADER = 'x-request-id';

    private const string REQUEST_ID_CONTEXT = 'request_id';

    private const string DEFAULT_EXCHANGE = '';

    private const int NANOSECONDS_PER_SECOND = 1_000_000_000;

    private const array DURATION_BUCKETS_SECONDS = [0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    private const string EPOCH_SECONDS = 'U.u';

    private const array END_TO_END_BUCKETS_SECONDS = [0.5, 1.0, 2.0, 3.0, 5.0, 10.0, 30.0, 60.0, 300.0, 900.0];

    public function __construct(
        private NotifyRequesterOfDecisionUseCase $notifyRequester,
        private DecisionMessageParser $parser,
        private DecisionConsumerSettings $settings,
        private LoggerInterface $logger,
        private Telemetry $telemetry,
        private Context $context,
        private Clock $clock,
    ) {}

    /**
     * The consume span continues the trace the message carries, so the delivery shows up under the request
     * that made the decision.
     */
    public function handle(AMQPMessage $message): void
    {
        $channel = $message->getChannel();
        \assert($channel instanceof AMQPChannel);
        $carrier = self::stringHeadersOf(message: $message);
        $startedAt = hrtime(true);
        $requestId = $carrier[self::REQUEST_ID_HEADER] ?? null;

        if ($requestId !== null) {
            $this->context->add(self::REQUEST_ID_CONTEXT, $requestId);
        }

        $outcome = $this->telemetry->inSpan(
            name: 'process ' . $this->settings->queue,
            work: fn(SpanInterface $span): string => $this->settle(channel: $channel, message: $message, span: $span),
            kind: SpanKind::KIND_CONSUMER,
            attributes: [
                'messaging.system' => 'rabbitmq',
                'messaging.operation.type' => 'process',
                'messaging.destination.name' => $this->settings->queue,
                'messaging.rabbitmq.destination.routing_key' => $message->getRoutingKey() ?? '',
            ],
            parent: $this->telemetry->contextFrom(carrier: $carrier),
        );

        $message->ack();
        $durationSeconds = (hrtime(true) - $startedAt) / self::NANOSECONDS_PER_SECOND;
        $this->record(outcome: $outcome, durationSeconds: $durationSeconds);
        $this->context->forget(self::REQUEST_ID_CONTEXT);
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

    /**
     * @return self::DELIVERED|self::DUPLICATE|self::RETRIED|self::DEAD_LETTERED
     */
    private function settle(AMQPChannel $channel, AMQPMessage $message, SpanInterface $span): string
    {
        try {
            $input = $this->parser->parse(body: $message->getBody());
            $delivery = $this->notifyRequester->execute(input: $input);
        } catch (UnreadableDecisionMessage|InvalidDecision|InvalidIdentifier $unreadable) {
            $span->recordException($unreadable);
            $this->deadLetter(
                channel: $channel,
                message: $message,
                reason: self::UNREADABLE,
                failure: $unreadable,
            );

            return self::DEAD_LETTERED;
        } catch (Throwable $failure) {
            $span->recordException($failure);

            return $this->retryOrDeadLetter(channel: $channel, message: $message, failure: $failure);
        }

        if ($delivery === NotificationDelivery::AlreadyDelivered) {
            return self::DUPLICATE;
        }

        $this->recordEndToEnd(decidedAt: $input->decidedAt);

        return self::DELIVERED;
    }

    /**
     * From the decision to its delivery, through the outbox, the relay, the broker and any retry. The event
     * time has one-second precision, so a delivery can read up to a second slower than it was.
     */
    private function recordEndToEnd(DateTimeImmutable $decidedAt): void
    {
        $deliveredAt = $this->clock->now();
        $deliveredAtSeconds = (float) $deliveredAt->format(self::EPOCH_SECONDS);
        $decidedAtSeconds = (float) $decidedAt->format(self::EPOCH_SECONDS);

        $this->telemetry->meter()
            ->createHistogram(
                'notification.end_to_end.duration',
                's',
                'Time from a decision to the delivery of its notification.',
                ['ExplicitBucketBoundaries' => self::END_TO_END_BUCKETS_SECONDS],
            )
            ->record($deliveredAtSeconds - $decidedAtSeconds);
    }

    private function record(string $outcome, float $durationSeconds): void
    {
        $meter = $this->telemetry->meter();
        $attributes = ['outcome' => $outcome];

        $meter->createCounter('notification.deliveries', '{message}', 'Decision messages settled, by outcome.')
            ->add(1, $attributes);
        $meter->createHistogram(
            'notification.delivery.duration',
            's',
            'Time to settle a decision message.',
            ['ExplicitBucketBoundaries' => self::DURATION_BUCKETS_SECONDS],
        )
            ->record($durationSeconds, $attributes);
    }

    /**
     * @return array<string, string>
     */
    private static function stringHeadersOf(AMQPMessage $message): array
    {
        $headers = [];

        foreach (self::headersOf(message: $message) as $name => $value) {
            if (\is_string($value)) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * @return self::RETRIED|self::DEAD_LETTERED
     */
    private function retryOrDeadLetter(AMQPChannel $channel, AMQPMessage $message, Throwable $failure): string
    {
        $retry = self::retryCountOf(message: $message) + 1;

        if ($retry > $this->settings->maxRetries()) {
            $this->deadLetter(
                channel: $channel,
                message: $message,
                reason: self::RETRIES_EXHAUSTED,
                failure: $failure,
            );

            return self::DEAD_LETTERED;
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

        return self::RETRIED;
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
