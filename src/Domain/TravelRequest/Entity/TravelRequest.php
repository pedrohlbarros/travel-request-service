<?php

declare(strict_types=1);

namespace Domain\TravelRequest\Entity;

use DateTimeImmutable;
use Domain\Shared\Event\DomainEvent;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\Event\TravelRequestApproved;
use Domain\TravelRequest\Event\TravelRequestCancelled;
use Domain\TravelRequest\Exception\InvalidStatusTransition;
use Domain\TravelRequest\Exception\InvalidTravelPeriod;
use Domain\TravelRequest\Exception\TravelRequestNotEditable;
use Domain\TravelRequest\Exception\TravelRequestNotOwned;
use Domain\TravelRequest\Exception\TravelRequestVersionMismatch;
use Domain\TravelRequest\ValueObject\Destination;
use Domain\TravelRequest\ValueObject\TravelPeriod;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Domain\TravelRequest\ValueObject\UserId;

final class TravelRequest
{
    public const int FIRST_VERSION = 1;

    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        public readonly TravelRequestId $id,
        public readonly UserId $requesterId,
        public private(set) Destination $destination,
        public private(set) TravelPeriod $period,
        public private(set) TravelRequestStatus $status,
        public private(set) int $version,
        public readonly DateTimeImmutable $createdAt,
        public private(set) DateTimeImmutable $updatedAt,
        public private(set) ?UserId $decidedBy,
    ) {}

    /**
     * @throws InvalidTravelPeriod
     */
    public static function request(
        TravelRequestId $id,
        UserId $requesterId,
        Destination $destination,
        TravelPeriod $period,
        DateTimeImmutable $requestedAt,
    ): self {
        $period->assertDepartsOnOrAfterDayOf(moment: $requestedAt);

        return new self(
            id: $id,
            requesterId: $requesterId,
            destination: $destination,
            period: $period,
            status: TravelRequestStatus::Requested,
            version: self::FIRST_VERSION,
            createdAt: $requestedAt,
            updatedAt: $requestedAt,
            decidedBy: null,
        );
    }

    public static function reconstitute(
        TravelRequestId $id,
        UserId $requesterId,
        Destination $destination,
        TravelPeriod $period,
        TravelRequestStatus $status,
        int $version,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?UserId $decidedBy,
    ): self {
        return new self(
            id: $id,
            requesterId: $requesterId,
            destination: $destination,
            period: $period,
            status: $status,
            version: $version,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            decidedBy: $decidedBy,
        );
    }

    /**
     * @throws TravelRequestVersionMismatch
     */
    public function assertVersionIs(int $expectedVersion): void
    {
        if ($this->version !== $expectedVersion) {
            throw TravelRequestVersionMismatch::expected(
                id: $this->id,
                expectedVersion: $expectedVersion,
                currentVersion: $this->version,
            );
        }
    }

    /**
     * @throws TravelRequestNotOwned
     */
    public function assertOwnedBy(UserId $userId): void
    {
        if (! $this->requesterId->equals(other: $userId)) {
            throw TravelRequestNotOwned::by(id: $this->id, userId: $userId);
        }
    }

    /**
     * Only the given details are changed; a missing date keeps its current value. Changing nothing for real
     * leaves the version alone, and the departure is only checked against today when the dates change, so a
     * trip that already started can still fix its destination.
     *
     * @throws TravelRequestNotOwned
     * @throws TravelRequestNotEditable
     * @throws InvalidTravelPeriod
     */
    public function changeDetails(
        UserId $editorId,
        ?Destination $destination,
        ?DateTimeImmutable $departureDate,
        ?DateTimeImmutable $returnDate,
        DateTimeImmutable $changedAt,
    ): void {
        $this->assertOwnedBy(userId: $editorId);

        if ($this->status !== TravelRequestStatus::Requested) {
            throw TravelRequestNotEditable::inStatus(id: $this->id, status: $this->status);
        }

        $newDestination = $destination ?? $this->destination;
        $newPeriod = TravelPeriod::between(
            departureDate: $departureDate ?? $this->period->departureDate,
            returnDate: $returnDate ?? $this->period->returnDate,
        );
        $isDestinationChanged = ! $newDestination->equals(other: $this->destination);
        $isPeriodChanged = ! $newPeriod->equals(other: $this->period);

        if (! $isDestinationChanged && ! $isPeriodChanged) {
            return;
        }

        if ($isPeriodChanged) {
            $newPeriod->assertDepartsOnOrAfterDayOf(moment: $changedAt);
        }

        $this->destination = $newDestination;
        $this->period = $newPeriod;
        $this->advanceVersion(changedAt: $changedAt);
    }

    /**
     * @throws InvalidStatusTransition
     */
    public function approve(UserId $approverId, DateTimeImmutable $approvedAt): void
    {
        $this->decide(targetStatus: TravelRequestStatus::Approved, deciderId: $approverId, decidedAt: $approvedAt);

        $this->recordedEvents[] = new TravelRequestApproved(
            travelRequestId: $this->id,
            requesterId: $this->requesterId,
            approvedBy: $approverId,
            destination: $this->destination,
            period: $this->period,
            version: $this->version,
            occurredAt: $approvedAt,
        );
    }

    /**
     * @throws InvalidStatusTransition
     */
    public function cancel(UserId $cancellerId, DateTimeImmutable $cancelledAt): void
    {
        $this->decide(targetStatus: TravelRequestStatus::Cancelled, deciderId: $cancellerId, decidedAt: $cancelledAt);

        $this->recordedEvents[] = new TravelRequestCancelled(
            travelRequestId: $this->id,
            requesterId: $this->requesterId,
            cancelledBy: $cancellerId,
            destination: $this->destination,
            period: $this->period,
            version: $this->version,
            occurredAt: $cancelledAt,
        );
    }

    /**
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $releasedEvents = $this->recordedEvents;
        $this->recordedEvents = [];

        return $releasedEvents;
    }

    /**
     * @throws InvalidStatusTransition
     */
    private function decide(TravelRequestStatus $targetStatus, UserId $deciderId, DateTimeImmutable $decidedAt): void
    {
        if ($this->status !== TravelRequestStatus::Requested) {
            throw InvalidStatusTransition::between(
                id: $this->id,
                currentStatus: $this->status,
                targetStatus: $targetStatus,
            );
        }

        $this->status = $targetStatus;
        $this->decidedBy = $deciderId;
        $this->advanceVersion(changedAt: $decidedAt);
    }

    private function advanceVersion(DateTimeImmutable $changedAt): void
    {
        $this->version++;
        $this->updatedAt = $changedAt;
    }
}
