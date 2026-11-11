<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Messaging\Outbox;

use Application\Shared\Port\DomainEventPublisher;
use Application\Shared\Port\IdentifierGenerator;
use DateTimeZone;
use Domain\Shared\Event\DomainEvent;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Log\Context\Repository as Context;
use Infrastructure\Shared\Observability\Telemetry;
use Infrastructure\Shared\Persistence\BinaryUuid;
use LogicException;

/**
 * Publishing means writing to the outbox, inside the caller's transaction; the relay takes it from there.
 */
final readonly class OutboxDomainEventPublisher implements DomainEventPublisher
{
    public const string TABLE = 'outbox_messages';

    public const string TRACEPARENT = 'traceparent';

    public const string REQUEST_ID = 'x-request-id';

    private const string REQUEST_ID_CONTEXT = 'request_id';

    public const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    private const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    private const string TIMEZONE = 'UTC';

    /**
     * @param iterable<EventMessageMapper> $mappers
     */
    public function __construct(
        private ConnectionInterface $connection,
        private iterable $mappers,
        private IdentifierGenerator $identifiers,
        private Context $context,
        private Telemetry $telemetry,
        private string $source,
    ) {}

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $mapper = $this->mapperFor(event: $event);
            $this->store(message: $mapper->toMessage(event: $event));
        }
    }

    private function store(EventMessage $message): void
    {
        $eventId = $this->identifiers->uuid7();
        $envelope = CloudEvent::envelope(message: $message, eventId: $eventId, source: $this->source);
        $occurredAtUtc = $message->occurredAt->setTimezone(new DateTimeZone(self::TIMEZONE));
        $payload = json_encode($envelope, self::JSON_FLAGS);
        $headers = json_encode($this->headers(), self::JSON_FLAGS | JSON_FORCE_OBJECT);

        $this->connection->table(self::TABLE)
            ->insert([
                'id' => BinaryUuid::toBytes(uuid: $eventId),
                'exchange' => $message->exchange,
                'routing_key' => $message->routingKey,
                'payload' => $payload,
                'headers' => $headers,
                'occurred_at' => $occurredAtUtc->format(self::DATETIME_FORMAT),
                'published_at' => null,
            ]);
    }

    private function mapperFor(DomainEvent $event): EventMessageMapper
    {
        foreach ($this->mappers as $mapper) {
            if ($mapper->supports(event: $event)) {
                return $mapper;
            }
        }

        throw new LogicException(\sprintf('No event message mapper supports %s.', $event::class));
    }

    /**
     * The current span when tracing is on; otherwise the traceparent the request came with, if any.
     *
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = $this->telemetry->propagationHeaders();
        $receivedTraceparent = $this->context->getHidden(self::TRACEPARENT);
        $requestId = $this->context->get(self::REQUEST_ID_CONTEXT);

        if (! isset($headers[self::TRACEPARENT]) && \is_string($receivedTraceparent)) {
            $headers[self::TRACEPARENT] = $receivedTraceparent;
        }

        if (\is_string($requestId)) {
            $headers[self::REQUEST_ID] = $requestId;
        }

        return $headers;
    }
}
