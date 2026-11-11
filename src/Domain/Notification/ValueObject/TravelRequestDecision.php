<?php

declare(strict_types=1);

namespace Domain\Notification\ValueObject;

use DateTimeImmutable;
use Domain\Notification\Enum\DecisionOutcome;
use Domain\Notification\Exception\InvalidDecision;
use Domain\Shared\Exception\InvalidIdentifier;
use Domain\Shared\ValueObject\Uuid7;

/**
 * An approval or cancellation the requester has to hear about, identified by the event that announced it.
 */
final readonly class TravelRequestDecision
{
    private function __construct(
        public Uuid7 $eventId,
        public Uuid7 $travelRequestId,
        public Recipient $recipient,
        public string $destination,
        public DateTimeImmutable $departureDate,
        public DateTimeImmutable $returnDate,
        public DecisionOutcome $outcome,
        public Uuid7 $decidedBy,
        public DateTimeImmutable $decidedAt,
    ) {}

    /**
     * @throws InvalidIdentifier
     * @throws InvalidDecision
     */
    public static function of(
        string $eventId,
        string $travelRequestId,
        Recipient $recipient,
        string $destination,
        DateTimeImmutable $departureDate,
        DateTimeImmutable $returnDate,
        string $outcome,
        string $decidedBy,
        DateTimeImmutable $decidedAt,
    ): self {
        $trimmedDestination = trim($destination);
        $decisionOutcome = DecisionOutcome::tryFrom(value: $outcome);

        if ($trimmedDestination === '') {
            throw InvalidDecision::blank(field: 'destination');
        }

        if ($decisionOutcome === null) {
            throw InvalidDecision::unknownOutcome(outcome: $outcome);
        }

        if ($returnDate < $departureDate) {
            throw InvalidDecision::returnBeforeDeparture();
        }

        return new self(
            eventId: Uuid7::fromString(candidate: $eventId),
            travelRequestId: Uuid7::fromString(candidate: $travelRequestId),
            recipient: $recipient,
            destination: $trimmedDestination,
            departureDate: $departureDate,
            returnDate: $returnDate,
            outcome: $decisionOutcome,
            decidedBy: Uuid7::fromString(candidate: $decidedBy),
            decidedAt: $decidedAt,
        );
    }
}
