<?php

declare(strict_types=1);

namespace Tests\Domain\Notification\ValueObject;

use DateTimeImmutable;
use Domain\Notification\Enum\DecisionOutcome;
use Domain\Notification\Exception\InvalidDecision;
use Domain\Notification\ValueObject\TravelRequestDecision;
use Domain\Shared\Exception\InvalidIdentifier;
use PHPUnit\Framework\TestCase;
use Tests\Support\Notification\Fixture\DecisionMother as Mother;

final class TravelRequestDecisionTest extends TestCase
{
    public function test_DescribesTheDecisionTheEventAnnounced(): void
    {
        $decision = $this->decision(destination: '  Wano  ');

        self::assertSame(Mother::EVENT_ID, $decision->eventId->value);
        self::assertSame(Mother::TRAVEL_REQUEST_ID, $decision->travelRequestId->value);
        self::assertSame(Mother::REQUESTER_ID, $decision->recipient->id->value);
        self::assertSame(Mother::DESTINATION, $decision->destination);
        self::assertEquals(Mother::date(date: Mother::DEPARTURE_DATE), $decision->departureDate);
        self::assertEquals(Mother::date(date: Mother::RETURN_DATE), $decision->returnDate);
        self::assertSame(DecisionOutcome::Approved, $decision->outcome);
        self::assertSame(Mother::ADMIN_ID, $decision->decidedBy->value);
        self::assertEquals(new DateTimeImmutable(Mother::DECIDED_AT), $decision->decidedAt);
    }

    public function test_ACancellationIsADecisionToo(): void
    {
        $decision = $this->decision(outcome: 'cancelled');

        self::assertSame(DecisionOutcome::Cancelled, $decision->outcome);
    }

    public function test_ATripOfASingleDayIsValid(): void
    {
        $decision = $this->decision(returnDate: Mother::DEPARTURE_DATE);

        self::assertEquals($decision->departureDate, $decision->returnDate);
    }

    public function test_RequiresADestination(): void
    {
        $this->expectException(InvalidDecision::class);
        $this->expectExceptionMessageIs('The decision has no destination.');

        $this->decision(destination: '  ');
    }

    public function test_RejectsAnUnknownOutcome(): void
    {
        $this->expectException(InvalidDecision::class);
        $this->expectExceptionMessageIs('"requested" is not a decision outcome.');

        $this->decision(outcome: 'requested');
    }

    public function test_RejectsAReturnBeforeTheDeparture(): void
    {
        $this->expectException(InvalidDecision::class);
        $this->expectExceptionMessageIs('The decision returns before it departs.');

        $this->decision(returnDate: '2026-11-30');
    }

    public function test_RequiresUuid7Ids(): void
    {
        $this->expectException(InvalidIdentifier::class);

        $this->decision(decidedBy: 'not-a-uuid');
    }

    private function decision(
        string $destination = Mother::DESTINATION,
        string $outcome = 'approved',
        string $returnDate = Mother::RETURN_DATE,
        string $decidedBy = Mother::ADMIN_ID,
    ): TravelRequestDecision {
        return TravelRequestDecision::of(
            eventId: Mother::EVENT_ID,
            travelRequestId: Mother::TRAVEL_REQUEST_ID,
            recipient: Mother::recipient(),
            destination: $destination,
            departureDate: Mother::date(date: Mother::DEPARTURE_DATE),
            returnDate: Mother::date(date: $returnDate),
            outcome: $outcome,
            decidedBy: $decidedBy,
            decidedAt: new DateTimeImmutable(Mother::DECIDED_AT),
        );
    }
}
