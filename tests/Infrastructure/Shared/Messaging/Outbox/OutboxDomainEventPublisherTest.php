<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Shared\Messaging\Outbox;

use DateTimeImmutable;
use DateTimeZone;
use Domain\Shared\Event\DomainEvent;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Context\Repository as Context;
use Infrastructure\Shared\Messaging\Outbox\OutboxDomainEventPublisher;
use LogicException;
use Tests\Support\LaravelTestCase;
use Tests\Support\Shared\Messaging\SampleEvent;
use Tests\Support\Shared\Messaging\SampleEventMessages;
use Tests\Support\Shared\Messaging\UsesTheOutbox;
use Tests\Support\Shared\Port\SequentialIdentifierGenerator;

final class OutboxDomainEventPublisherTest extends LaravelTestCase
{
    use RefreshDatabase;
    use UsesTheOutbox;

    private const string SOURCE = '/travel-request-service';

    private const string SUBJECT = 'sample-subject';

    private const string TRACEPARENT = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

    private const string OCCURRED_AT = '2026-11-10 09:30:15';

    public function test_StoresEachEventAsAPendingCloudEvent(): void
    {
        $occurredAt = new DateTimeImmutable(self::OCCURRED_AT);
        $firstEvent = new SampleEvent(subject: self::SUBJECT, occurredAt: $occurredAt);
        $secondEvent = new SampleEvent(subject: 'another-subject', occurredAt: $occurredAt);

        $this->publisher()
            ->publish($firstEvent, $secondEvent);

        $envelope = json_decode($this->outboxColumn(column: 'payload')[0], true);
        $exchanges = array_unique($this->outboxColumn(column: 'exchange'));
        $routingKeys = array_unique($this->outboxColumn(column: 'routing_key'));

        self::assertSame(
            [SequentialIdentifierGenerator::nth(sequence: 1), SequentialIdentifierGenerator::nth(sequence: 2)],
            $this->pendingOutboxIds(),
        );
        self::assertSame([SampleEventMessages::EXCHANGE], $exchanges);
        self::assertSame([SampleEventMessages::ROUTING_KEY], $routingKeys);
        self::assertSame(self::OCCURRED_AT, $this->outboxColumn(column: 'occurred_at')[0]);
        self::assertSame(
            [
                'specversion' => '1.0',
                'id' => SequentialIdentifierGenerator::nth(sequence: 1),
                'source' => self::SOURCE,
                'type' => SampleEventMessages::ROUTING_KEY,
                'subject' => self::SUBJECT,
                'time' => '2026-11-10T09:30:15+00:00',
                'datacontenttype' => 'application/json',
                'data' => ['subject' => self::SUBJECT],
            ],
            $envelope,
        );
    }

    public function test_TheTimeIsInUtcWhateverTheEventTimezone(): void
    {
        $occurredInSaoPaulo = new DateTimeImmutable('2026-11-10 06:30:15', new DateTimeZone('America/Sao_Paulo'));

        $this->publisher()
            ->publish(new SampleEvent(subject: self::SUBJECT, occurredAt: $occurredInSaoPaulo));

        $envelope = json_decode($this->outboxColumn(column: 'payload')[0], true);

        self::assertSame(self::OCCURRED_AT, $this->outboxColumn(column: 'occurred_at')[0]);
        self::assertIsArray($envelope);
        self::assertSame('2026-11-10T09:30:15+00:00', $envelope['time']);
    }

    public function test_CarriesTheTraceparentOfTheRequest(): void
    {
        $this->app->make(Context::class)
            ->addHidden(OutboxDomainEventPublisher::TRACEPARENT, self::TRACEPARENT);

        $this->publisher()
            ->publish($this->sampleEvent());

        $headers = json_decode($this->outboxColumn(column: 'headers')[0], true);

        self::assertSame([OutboxDomainEventPublisher::TRACEPARENT => self::TRACEPARENT], $headers);
    }

    public function test_HasNoHeadersOutsideATracedRequest(): void
    {
        $this->publisher()
            ->publish($this->sampleEvent());

        $headers = $this->outboxColumn(column: 'headers')[0];

        self::assertSame('{}', $headers);
    }

    public function test_AnEventWithoutAMapperIsAProgrammingError(): void
    {
        $unmappedEvent = new class implements DomainEvent {};

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('No event message mapper supports');

        $this->publisher()
            ->publish($unmappedEvent);
    }

    private function publisher(): OutboxDomainEventPublisher
    {
        return new OutboxDomainEventPublisher(
            connection: $this->app->make(ConnectionInterface::class),
            mappers: [new SampleEventMessages()],
            identifiers: new SequentialIdentifierGenerator(),
            context: $this->app->make(Context::class),
            source: self::SOURCE,
        );
    }

    private function sampleEvent(): SampleEvent
    {
        return new SampleEvent(subject: self::SUBJECT, occurredAt: new DateTimeImmutable(self::OCCURRED_AT));
    }
}
