<?php

declare(strict_types=1);

namespace Tests\Support\Shared\Messaging;

use Domain\Shared\Event\DomainEvent;
use Infrastructure\Shared\Messaging\Outbox\EventMessage;
use Infrastructure\Shared\Messaging\Outbox\EventMessageMapper;

final readonly class SampleEventMessages implements EventMessageMapper
{
    public const string EXCHANGE = 'shared.events.test';

    public const string ROUTING_KEY = 'sample.happened.v1';

    public function supports(DomainEvent $event): bool
    {
        return $event instanceof SampleEvent;
    }

    public function toMessage(DomainEvent $event): EventMessage
    {
        \assert($event instanceof SampleEvent);

        return new EventMessage(
            exchange: self::EXCHANGE,
            routingKey: self::ROUTING_KEY,
            subject: $event->subject,
            eventData: ['subject' => $event->subject],
            occurredAt: $event->occurredAt,
        );
    }
}
