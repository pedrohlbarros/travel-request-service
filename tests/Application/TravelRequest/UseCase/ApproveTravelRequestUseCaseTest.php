<?php

declare(strict_types=1);

namespace Tests\Application\TravelRequest\UseCase;

use Application\TravelRequest\Input\ApproveTravelRequestInput;
use Application\TravelRequest\Output\TravelRequestOutput;
use Application\TravelRequest\UseCase\ApproveTravelRequestUseCase;
use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\Event\TravelRequestApproved;
use Domain\TravelRequest\Exception\InvalidStatusTransition;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\Exception\TravelRequestVersionMismatch;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use PHPUnit\Framework\TestCase;
use Tests\Support\Shared\Port\FrozenClock;
use Tests\Support\Shared\Port\SpyDomainEventPublisher;
use Tests\Support\TravelRequest\Fixture\TravelRequestMother as Mother;
use Tests\Support\TravelRequest\Repository\InMemoryTravelRequestRepository;

final class ApproveTravelRequestUseCaseTest extends TestCase
{
    private InMemoryTravelRequestRepository $travelRequests;

    private SpyDomainEventPublisher $events;

    private ApproveTravelRequestUseCase $useCase;

    protected function setUp(): void
    {
        $clock = new FrozenClock(frozenAt: Mother::moment(moment: Mother::LATER));
        $this->travelRequests = new InMemoryTravelRequestRepository();
        $this->events = new SpyDomainEventPublisher();
        $this->useCase = new ApproveTravelRequestUseCase(
            travelRequests: $this->travelRequests,
            events: $this->events,
            clock: $clock,
        );
    }

    public function test_AdminApprovesRequestedTravelRequest(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());

        $changed = $this->useCase->execute(input: new ApproveTravelRequestInput(
            travelRequestId: Mother::TRAVEL_REQUEST_ID,
            approverId: Mother::ADMIN_ID,
            expectedVersion: TravelRequest::FIRST_VERSION,
        ));

        $stored = $this->storedTravelRequest();

        self::assertEquals(TravelRequestOutput::fromEntity(travelRequest: $stored), $changed);
        self::assertSame(TravelRequestStatus::Approved, $stored->status);
        self::assertSame(Mother::ADMIN_ID, $stored->decidedBy?->toString());
        self::assertSame(TravelRequest::FIRST_VERSION + 1, $stored->version);
        self::assertEquals(Mother::moment(moment: Mother::LATER), $stored->updatedAt);
    }

    public function test_PublishesApprovedEventOnce(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());

        $this->useCase->execute(input: new ApproveTravelRequestInput(
            travelRequestId: Mother::TRAVEL_REQUEST_ID,
            approverId: Mother::ADMIN_ID,
            expectedVersion: TravelRequest::FIRST_VERSION,
        ));

        $published = $this->events->publishedEvents[0] ?? null;

        self::assertCount(1, $this->events->publishedEvents);
        self::assertInstanceOf(TravelRequestApproved::class, $published);
        self::assertSame(Mother::TRAVEL_REQUEST_ID, $published->travelRequestId->toString());
        self::assertSame(Mother::ADMIN_ID, $published->approvedBy->toString());
    }

    public function test_RejectsStaleExpectedVersion(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());
        $staleVersion = TravelRequest::FIRST_VERSION - 1;

        try {
            $this->useCase->execute(input: new ApproveTravelRequestInput(
                travelRequestId: Mother::TRAVEL_REQUEST_ID,
                approverId: Mother::ADMIN_ID,
                expectedVersion: $staleVersion,
            ));
            self::fail('A stale version must be rejected.');
        } catch (TravelRequestVersionMismatch) {
        }

        self::assertSame(TravelRequestStatus::Requested, $this->storedTravelRequest()->status);
        self::assertSame([], $this->events->publishedEvents);
    }

    public function test_CannotApproveCancelledTravelRequest(): void
    {
        $alreadyDecided = Mother::cancelled();
        $this->travelRequests->add(travelRequest: $alreadyDecided);

        try {
            $this->useCase->execute(input: new ApproveTravelRequestInput(
                travelRequestId: Mother::TRAVEL_REQUEST_ID,
                approverId: Mother::ADMIN_ID,
                expectedVersion: $alreadyDecided->version,
            ));
            self::fail('A decided travel request must not change status again.');
        } catch (InvalidStatusTransition) {
        }

        self::assertSame(TravelRequestStatus::Cancelled, $this->storedTravelRequest()->status);
        self::assertSame([], $this->events->publishedEvents);
    }

    public function test_AdminCanApproveOwnTravelRequest(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested(requesterId: Mother::ADMIN_ID));

        $this->useCase->execute(input: new ApproveTravelRequestInput(
            travelRequestId: Mother::TRAVEL_REQUEST_ID,
            approverId: Mother::ADMIN_ID,
            expectedVersion: TravelRequest::FIRST_VERSION,
        ));

        self::assertSame(TravelRequestStatus::Approved, $this->storedTravelRequest()->status);
    }

    public function test_FailsForUnknownTravelRequest(): void
    {
        $this->expectException(TravelRequestNotFound::class);

        $this->useCase->execute(input: new ApproveTravelRequestInput(
            travelRequestId: Mother::TRAVEL_REQUEST_ID,
            approverId: Mother::ADMIN_ID,
            expectedVersion: TravelRequest::FIRST_VERSION,
        ));
    }

    private function storedTravelRequest(): TravelRequest
    {
        return $this->travelRequests->get(id: TravelRequestId::fromString(id: Mother::TRAVEL_REQUEST_ID));
    }
}
