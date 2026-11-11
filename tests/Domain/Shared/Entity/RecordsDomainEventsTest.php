<?php

declare(strict_types=1);

namespace Tests\Domain\Shared\Entity;

use Domain\Shared\Event\DomainEvent;
use PHPUnit\Framework\TestCase;
use Tests\Support\Shared\Entity\EventRecordingAggregate;

final class RecordsDomainEventsTest extends TestCase
{
    public function test_NothingRecordedReleasesNothing(): void
    {
        $aggregate = $this->aggregate();

        $releasedEvents = $aggregate->releaseEvents();

        self::assertSame([], $releasedEvents);
    }

    public function test_ReleasesEveryRecordedEventInOrder(): void
    {
        $aggregate = $this->aggregate();
        $firstEvent = new class implements DomainEvent {};
        $secondEvent = new class implements DomainEvent {};
        $aggregate->happen(event: $firstEvent);
        $aggregate->happen(event: $secondEvent);

        $releasedEvents = $aggregate->releaseEvents();

        self::assertSame([$firstEvent, $secondEvent], $releasedEvents);
    }

    public function test_EventsAreReleasedOnlyOnce(): void
    {
        $aggregate = $this->aggregate();
        $aggregate->happen(event: new class implements DomainEvent {});
        $aggregate->releaseEvents();

        $releasedAgain = $aggregate->releaseEvents();

        self::assertSame([], $releasedAgain);
    }

    private function aggregate(): EventRecordingAggregate
    {
        return new EventRecordingAggregate();
    }
}
