<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Notification\Persistence;

use DateTimeImmutable;
use Domain\Shared\ValueObject\Uuid7;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Notification\Persistence\DatabaseProcessedEvents;
use Tests\Support\LaravelTestCase;
use Tests\Support\Notification\Fixture\DecisionMother as Mother;
use Tests\Support\Shared\Port\FrozenClock;

final class DatabaseProcessedEventsTest extends LaravelTestCase
{
    use RefreshDatabase;

    private const string PROCESSED_AT = '2026-11-10 09:31:00';

    public function test_RecordsAnEventOnceAndRecognizesItAfterwards(): void
    {
        $processedEvents = $this->processedEvents();
        $eventId = Uuid7::fromString(candidate: Mother::EVENT_ID);

        $isFirstDelivery = $processedEvents->recordFirstDelivery(eventId: $eventId);
        $isSecondDeliveryFirst = $processedEvents->recordFirstDelivery(eventId: $eventId);

        $row = $this->app->make(ConnectionInterface::class)
            ->table(DatabaseProcessedEvents::TABLE)
            ->first(['consumer', 'processed_at']);

        self::assertTrue($isFirstDelivery);
        self::assertFalse($isSecondDeliveryFirst);
        self::assertNotNull($row);
        self::assertSame(DatabaseProcessedEvents::CONSUMER, $row->consumer);
        self::assertSame(self::PROCESSED_AT, $row->processed_at);
    }

    public function test_DifferentEventsAreEachAFirstDelivery(): void
    {
        $processedEvents = $this->processedEvents();
        $processedEvents->recordFirstDelivery(eventId: Uuid7::fromString(candidate: Mother::EVENT_ID));

        $isOtherFirstDelivery = $processedEvents->recordFirstDelivery(eventId: Uuid7::fromString(candidate: Mother::OTHER_EVENT_ID));

        self::assertTrue($isOtherFirstDelivery);
    }

    private function processedEvents(): DatabaseProcessedEvents
    {
        $processedAt = new DateTimeImmutable(self::PROCESSED_AT);

        return new DatabaseProcessedEvents(
            connection: $this->app->make(ConnectionInterface::class),
            clock: new FrozenClock(frozenAt: $processedAt),
        );
    }
}
