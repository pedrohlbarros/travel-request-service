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
        $period->assertDepartsOnOrAfterDayOf($requestedAt);

        return new self(
            $id,
            $requesterId,
            $destination,
            $period,
            TravelRequestStatus::Requested,
            self::FIRST_VERSION,
            $requestedAt,
            $requestedAt,
            null,
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
        return new self($id, $requesterId, $destination, $period, $status, $version, $createdAt, $updatedAt, $decidedBy);
    }

    /**
     * @throws TravelRequestVersionMismatch
     */
    public function assertVersionIs(int $expectedVersion): void
    {
        if ($this->version !== $expectedVersion) {
            throw TravelRequestVersionMismatch::expected($this->id, $expectedVersion, $this->version);
        }
    }

    /**
     * @throws TravelRequestNotOwned
     * @throws TravelRequestNotEditable
     * @throws InvalidTravelPeriod
     */
    public function changeDetails(
        UserId $editorId,
        Destination $destination,
        TravelPeriod $period,
        DateTimeImmutable $changedAt,
    ): void {
        if (! $this->requesterId->equals($editorId)) {
            throw TravelRequestNotOwned::by($this->id, $editorId);
        }

        if ($this->status !== TravelRequestStatus::Requested) {
            throw TravelRequestNotEditable::inStatus($this->id, $this->status);
        }

        $period->assertDepartsOnOrAfterDayOf($changedAt);

        $this->destination = $destination;
        $this->period = $period;
        $this->advanceVersion($changedAt);
    }

    /**
     * @throws InvalidStatusTransition
     */
    public function approve(UserId $approverId, DateTimeImmutable $approvedAt): void
    {
        $this->decide(TravelRequestStatus::Approved, $approverId, $approvedAt);

        $this->recordedEvents[] = new TravelRequestApproved(
            $this->id,
            $this->requesterId,
            $approverId,
            $this->destination,
            $this->period,
            $this->version,
            $approvedAt,
        );
    }

    /**
     * @throws InvalidStatusTransition
     */
    public function cancel(UserId $cancellerId, DateTimeImmutable $cancelledAt): void
    {
        $this->decide(TravelRequestStatus::Cancelled, $cancellerId, $cancelledAt);

        $this->recordedEvents[] = new TravelRequestCancelled(
            $this->id,
            $this->requesterId,
            $cancellerId,
            $this->destination,
            $this->period,
            $this->version,
            $cancelledAt,
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
            throw InvalidStatusTransition::between($this->id, $this->status, $targetStatus);
        }

        $this->status = $targetStatus;
        $this->decidedBy = $deciderId;
        $this->advanceVersion($decidedAt);
    }

    private function advanceVersion(DateTimeImmutable $changedAt): void
    {
        $this->version++;
        $this->updatedAt = $changedAt;
    }
}
