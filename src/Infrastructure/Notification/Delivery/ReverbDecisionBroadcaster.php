<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Delivery;

use Application\Notification\Port\DecisionBroadcaster;
use Application\Shared\Port\Clock;
use DateTimeInterface;
use DateTimeZone;
use Domain\Notification\ValueObject\TravelRequestDecision;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\Factory as Broadcasting;
use Infrastructure\Shared\Observability\Telemetry;
use OpenTelemetry\API\Trace\SpanKind;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class ReverbDecisionBroadcaster implements DecisionBroadcaster
{
    public const string EVENT = 'travel_request.decided';

    public const string CHANNEL_PREFIX = 'users.';

    private const string TIMEZONE = 'UTC';

    public function __construct(
        private Broadcasting $broadcasting,
        private DecisionTextsFactory $texts,
        private Clock $clock,
        private LoggerInterface $logger,
        private Telemetry $telemetry,
    ) {}

    public function broadcast(TravelRequestDecision $decision): void
    {
        $channel = new PrivateChannel(self::CHANNEL_PREFIX . $decision->recipient->id->value);
        $createdAt = $this->clock->now()
            ->setTimezone(new DateTimeZone(self::TIMEZONE));
        $payload = [
            'id' => $decision->eventId->value,
            'type' => DecisionDetails::typeOf(decision: $decision),
            'message' => $this->texts->for(decision: $decision)->message,
            'data' => DecisionDetails::of(decision: $decision),
            'created_at' => $createdAt->format(DateTimeInterface::ATOM),
        ];

        try {
            $this->telemetry->inSpan(
                name: 'notification.broadcast',
                work: function () use ($channel, $payload): void {
                    $this->broadcasting->connection()
                        ->broadcast([$channel], self::EVENT, $payload);
                },
                kind: SpanKind::KIND_CLIENT,
                attributes: ['messaging.destination.name' => (string) $channel],
            );
        } catch (Throwable $exception) {
            $this->logger->warning(
                'Broadcast of a decision failed; the stored notification stays readable.',
                ['event_id' => $decision->eventId->value, 'exception' => $exception],
            );
        }
    }
}
