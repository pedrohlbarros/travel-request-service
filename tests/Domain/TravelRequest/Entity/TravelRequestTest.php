<?php

declare(strict_types=1);

namespace Tests\Domain\TravelRequest\Entity;

use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\Event\TravelRequestApproved;
use Domain\TravelRequest\Event\TravelRequestCancelled;
use Domain\TravelRequest\Exception\InvalidStatusTransition;
use Domain\TravelRequest\Exception\InvalidTravelPeriod;
use Domain\TravelRequest\Exception\TravelRequestNotEditable;
use Domain\TravelRequest\Exception\TravelRequestNotOwned;
use Domain\TravelRequest\Exception\TravelRequestVersionMismatch;
use Domain\TravelRequest\ValueObject\Destination;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Domain\TravelRequest\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Tests\Support\TravelRequest\Fixture\TravelRequestMother as Mother;

final class TravelRequestTest extends TestCase
{
    private const string NEW_DESTINATION = 'Tokyo';

    private const string CALENDAR_DATE = 'Y-m-d';

    public function test_NewTravelRequestStartsRequestedAtFirstVersion(): void
    {
        $requestedAt = Mother::moment(moment: Mother::NOW);

        $travelRequest = Mother::requested();

        self::assertSame(
            Mother::TRAVEL_REQUEST_ID,
            $travelRequest->id->toString(),
        );
        self::assertSame(
            Mother::REQUESTER_ID,
            $travelRequest->requesterId->toString(),
        );
        self::assertSame(
            Mother::DESTINATION,
            $travelRequest->destination->name,
        );
        self::assertSame(
            Mother::DEPARTURE_DATE,
            $travelRequest->period->departureDate->format(self::CALENDAR_DATE),
        );
        self::assertSame(
            Mother::RETURN_DATE,
            $travelRequest->period->returnDate->format(self::CALENDAR_DATE),
        );
        self::assertSame(
            TravelRequestStatus::Requested,
            $travelRequest->status,
        );
        self::assertSame(
            TravelRequest::FIRST_VERSION,
            $travelRequest->version,
        );
        self::assertEquals(
            $requestedAt,
            $travelRequest->createdAt,
        );
        self::assertEquals(
            $requestedAt,
            $travelRequest->updatedAt,
        );
        self::assertNull($travelRequest->decidedBy);
        self::assertSame(
            [],
            $travelRequest->releaseEvents(),
        );
    }

    public function test_CannotRequestTravelDepartingBeforeToday(): void
    {
        $this->expectException(InvalidTravelPeriod::class);

        TravelRequest::request(
            id: TravelRequestId::fromString(id: Mother::TRAVEL_REQUEST_ID),
            requesterId: UserId::fromString(id: Mother::REQUESTER_ID),
            destination: Destination::fromString(name: Mother::DESTINATION),
            period: Mother::period(
                departureDate: '2026-11-08',
                returnDate: '2026-11-12',
            ),
            requestedAt: Mother::moment(moment: Mother::NOW),
        );
    }

    public function test_CanRequestTravelDepartingToday(): void
    {
        $travelRequest = TravelRequest::request(
            id: TravelRequestId::fromString(id: Mother::TRAVEL_REQUEST_ID),
            requesterId: UserId::fromString(id: Mother::REQUESTER_ID),
            destination: Destination::fromString(name: Mother::DESTINATION),
            period: Mother::period(
                departureDate: '2026-11-09',
                returnDate: '2026-11-09',
            ),
            requestedAt: Mother::moment(moment: Mother::NOW),
        );

        self::assertSame(
            TravelRequestStatus::Requested,
            $travelRequest->status,
        );
    }

    public function test_RequesterChangesDetailsWhileRequested(): void
    {
        $travelRequest = Mother::requested();
        $changedAt = Mother::moment(moment: Mother::LATER);

        $travelRequest->changeDetails(
            editorId: Mother::userId(id: Mother::REQUESTER_ID),
            destination: Destination::fromString(name: self::NEW_DESTINATION),
            period: Mother::period(
                departureDate: '2026-12-10',
                returnDate: '2026-12-20',
            ),
            changedAt: $changedAt,
        );

        self::assertSame(
            self::NEW_DESTINATION,
            $travelRequest->destination->name,
        );
        self::assertSame(
            '2026-12-10',
            $travelRequest->period->departureDate->format(self::CALENDAR_DATE),
        );
        self::assertSame(
            '2026-12-20',
            $travelRequest->period->returnDate->format(self::CALENDAR_DATE),
        );
        self::assertSame(
            TravelRequest::FIRST_VERSION + 1,
            $travelRequest->version,
        );
        self::assertEquals(
            $changedAt,
            $travelRequest->updatedAt,
        );
        self::assertEquals(
            Mother::moment(moment: Mother::NOW),
            $travelRequest->createdAt,
        );
        self::assertSame(
            TravelRequestStatus::Requested,
            $travelRequest->status,
        );
        self::assertSame(
            [],
            $travelRequest->releaseEvents(),
        );
    }

    public function test_OnlyTheRequesterChangesDetails(): void
    {
        $travelRequest = Mother::requested();

        $this->expectException(TravelRequestNotOwned::class);

        $travelRequest->changeDetails(
            editorId: Mother::userId(id: Mother::ADMIN_ID),
            destination: Destination::fromString(name: self::NEW_DESTINATION),
            period: Mother::period(),
            changedAt: Mother::moment(moment: Mother::LATER),
        );
    }

    public function test_CannotChangeDetailsOfApprovedTravelRequest(): void
    {
        $travelRequest = Mother::approved();

        $this->expectException(TravelRequestNotEditable::class);
        $this->expectExceptionMessageIsOrContains('is approved and can no longer be edited');

        $travelRequest->changeDetails(
            editorId: Mother::userId(id: Mother::REQUESTER_ID),
            destination: Destination::fromString(name: self::NEW_DESTINATION),
            period: Mother::period(),
            changedAt: Mother::moment(moment: Mother::LATER),
        );
    }

    public function test_CannotChangeDetailsOfCancelledTravelRequest(): void
    {
        $travelRequest = Mother::cancelled();

        $this->expectException(TravelRequestNotEditable::class);
        $this->expectExceptionMessageIsOrContains('is cancelled and can no longer be edited');

        $travelRequest->changeDetails(
            editorId: Mother::userId(id: Mother::REQUESTER_ID),
            destination: Destination::fromString(name: self::NEW_DESTINATION),
            period: Mother::period(),
            changedAt: Mother::moment(moment: Mother::LATER),
        );
    }

    public function test_CannotChangeDetailsToDepartureBeforeToday(): void
    {
        $travelRequest = Mother::requested();

        $this->expectException(InvalidTravelPeriod::class);

        $travelRequest->changeDetails(
            editorId: Mother::userId(id: Mother::REQUESTER_ID),
            destination: Destination::fromString(name: self::NEW_DESTINATION),
            period: Mother::period(
                departureDate: '2026-11-09',
                returnDate: '2026-11-12',
            ),
            changedAt: Mother::moment(moment: Mother::LATER),
        );
    }

    public function test_RejectedChangeKeepsTravelRequestUntouched(): void
    {
        $travelRequest = Mother::requested();

        try {
            $travelRequest->changeDetails(
                editorId: Mother::userId(id: Mother::OTHER_USER_ID),
                destination: Destination::fromString(name: self::NEW_DESTINATION),
                period: Mother::period(),
                changedAt: Mother::moment(moment: Mother::LATER),
            );
        } catch (TravelRequestNotOwned) {
        }

        self::assertSame(
            Mother::DESTINATION,
            $travelRequest->destination->name,
        );
        self::assertSame(
            TravelRequest::FIRST_VERSION,
            $travelRequest->version,
        );
    }

    public function test_AdminApprovesRequestedTravelRequest(): void
    {
        $travelRequest = Mother::requested();
        $approvedAt = Mother::moment(moment: Mother::LATER);

        $travelRequest->approve(
            approverId: Mother::userId(id: Mother::ADMIN_ID),
            approvedAt: $approvedAt,
        );

        self::assertSame(
            TravelRequestStatus::Approved,
            $travelRequest->status,
        );
        self::assertSame(
            Mother::ADMIN_ID,
            $travelRequest->decidedBy?->toString(),
        );
        self::assertSame(
            TravelRequest::FIRST_VERSION + 1,
            $travelRequest->version,
        );
        self::assertEquals(
            $approvedAt,
            $travelRequest->updatedAt,
        );
    }

    public function test_ApprovalRecordsTravelRequestApproved(): void
    {
        $travelRequest = Mother::requested();
        $approvedAt = Mother::moment(moment: Mother::LATER);

        $travelRequest->approve(
            approverId: Mother::userId(id: Mother::ADMIN_ID),
            approvedAt: $approvedAt,
        );

        $releasedEvents = $travelRequest->releaseEvents();

        $approved = $releasedEvents[0] ?? null;

        self::assertCount(
            1,
            $releasedEvents,
        );
        self::assertInstanceOf(
            TravelRequestApproved::class,
            $approved,
        );
        self::assertSame(
            Mother::TRAVEL_REQUEST_ID,
            $approved->travelRequestId->toString(),
        );
        self::assertSame(
            Mother::REQUESTER_ID,
            $approved->requesterId->toString(),
        );
        self::assertSame(
            Mother::ADMIN_ID,
            $approved->approvedBy->toString(),
        );
        self::assertSame(
            Mother::DESTINATION,
            $approved->destination->name,
        );
        self::assertEquals(
            Mother::period(),
            $approved->period,
        );
        self::assertSame(
            TravelRequest::FIRST_VERSION + 1,
            $approved->version,
        );
        self::assertEquals(
            $approvedAt,
            $approved->occurredAt,
        );
    }

    public function test_AdminCancelsRequestedTravelRequest(): void
    {
        $travelRequest = Mother::requested();
        $cancelledAt = Mother::moment(moment: Mother::LATER);

        $travelRequest->cancel(
            cancellerId: Mother::userId(id: Mother::ADMIN_ID),
            cancelledAt: $cancelledAt,
        );

        self::assertSame(
            TravelRequestStatus::Cancelled,
            $travelRequest->status,
        );
        self::assertSame(
            Mother::ADMIN_ID,
            $travelRequest->decidedBy?->toString(),
        );
        self::assertSame(
            TravelRequest::FIRST_VERSION + 1,
            $travelRequest->version,
        );
        self::assertEquals(
            $cancelledAt,
            $travelRequest->updatedAt,
        );
    }

    public function test_CancellationRecordsTravelRequestCancelled(): void
    {
        $travelRequest = Mother::requested();
        $cancelledAt = Mother::moment(moment: Mother::LATER);

        $travelRequest->cancel(
            cancellerId: Mother::userId(id: Mother::ADMIN_ID),
            cancelledAt: $cancelledAt,
        );

        $releasedEvents = $travelRequest->releaseEvents();

        $cancelled = $releasedEvents[0] ?? null;

        self::assertCount(
            1,
            $releasedEvents,
        );
        self::assertInstanceOf(
            TravelRequestCancelled::class,
            $cancelled,
        );
        self::assertSame(
            Mother::TRAVEL_REQUEST_ID,
            $cancelled->travelRequestId->toString(),
        );
        self::assertSame(
            Mother::REQUESTER_ID,
            $cancelled->requesterId->toString(),
        );
        self::assertSame(
            Mother::ADMIN_ID,
            $cancelled->cancelledBy->toString(),
        );
        self::assertSame(
            Mother::DESTINATION,
            $cancelled->destination->name,
        );
        self::assertEquals(
            Mother::period(),
            $cancelled->period,
        );
        self::assertSame(
            TravelRequest::FIRST_VERSION + 1,
            $cancelled->version,
        );
        self::assertEquals(
            $cancelledAt,
            $cancelled->occurredAt,
        );
    }

    public function test_AdminCanApproveOwnTravelRequest(): void
    {
        $adminOwnTravelRequest = Mother::requested(requesterId: Mother::ADMIN_ID);

        $adminOwnTravelRequest->approve(
            approverId: Mother::userId(id: Mother::ADMIN_ID),
            approvedAt: Mother::moment(moment: Mother::LATER),
        );

        self::assertSame(
            TravelRequestStatus::Approved,
            $adminOwnTravelRequest->status,
        );
    }

    public function test_AdminCanCancelOwnTravelRequest(): void
    {
        $adminOwnTravelRequest = Mother::requested(requesterId: Mother::ADMIN_ID);

        $adminOwnTravelRequest->cancel(
            cancellerId: Mother::userId(id: Mother::ADMIN_ID),
            cancelledAt: Mother::moment(moment: Mother::LATER),
        );

        self::assertSame(
            TravelRequestStatus::Cancelled,
            $adminOwnTravelRequest->status,
        );
    }

    public function test_CannotCancelApprovedTravelRequest(): void
    {
        $travelRequest = Mother::approved();

        $this->expectException(InvalidStatusTransition::class);
        $this->expectExceptionMessageIsOrContains('cannot go from approved to cancelled');

        $travelRequest->cancel(
            cancellerId: Mother::userId(id: Mother::ADMIN_ID),
            cancelledAt: Mother::moment(moment: Mother::LATER),
        );
    }

    public function test_CannotApproveCancelledTravelRequest(): void
    {
        $travelRequest = Mother::cancelled();

        $this->expectException(InvalidStatusTransition::class);
        $this->expectExceptionMessageIsOrContains('cannot go from cancelled to approved');

        $travelRequest->approve(
            approverId: Mother::userId(id: Mother::ADMIN_ID),
            approvedAt: Mother::moment(moment: Mother::LATER),
        );
    }

    public function test_CannotApproveTwice(): void
    {
        $travelRequest = Mother::approved();

        $this->expectException(InvalidStatusTransition::class);

        $travelRequest->approve(
            approverId: Mother::userId(id: Mother::ADMIN_ID),
            approvedAt: Mother::moment(moment: Mother::LATER),
        );
    }

    public function test_CannotCancelTwice(): void
    {
        $travelRequest = Mother::cancelled();

        $this->expectException(InvalidStatusTransition::class);

        $travelRequest->cancel(
            cancellerId: Mother::userId(id: Mother::ADMIN_ID),
            cancelledAt: Mother::moment(moment: Mother::LATER),
        );
    }

    public function test_RejectedTransitionRecordsNoEventAndKeepsVersion(): void
    {
        $travelRequest = Mother::approved();
        $versionAfterApproval = $travelRequest->version;

        try {
            $travelRequest->cancel(
                cancellerId: Mother::userId(id: Mother::ADMIN_ID),
                cancelledAt: Mother::moment(moment: Mother::LATER),
            );
        } catch (InvalidStatusTransition) {
        }

        self::assertSame(
            $versionAfterApproval,
            $travelRequest->version,
        );
        self::assertSame(
            TravelRequestStatus::Approved,
            $travelRequest->status,
        );
        self::assertSame(
            [],
            $travelRequest->releaseEvents(),
        );
    }

    public function test_ReleasingEventsEmptiesTheRecord(): void
    {
        $travelRequest = Mother::requested();
        $travelRequest->approve(
            approverId: Mother::userId(id: Mother::ADMIN_ID),
            approvedAt: Mother::moment(moment: Mother::LATER),
        );

        $travelRequest->releaseEvents();

        self::assertSame(
            [],
            $travelRequest->releaseEvents(),
        );
    }

    public function test_AcceptsExpectedVersionWhenItMatches(): void
    {
        $travelRequest = Mother::requested();

        $travelRequest->assertVersionIs(expectedVersion: TravelRequest::FIRST_VERSION);

        $this->addToAssertionCount(1);
    }

    public function test_RejectsExpectedVersionThatDiffers(): void
    {
        $travelRequest = Mother::approved();

        $this->expectException(TravelRequestVersionMismatch::class);
        $this->expectExceptionMessageIsOrContains('is at version 2, not 1');

        $travelRequest->assertVersionIs(expectedVersion: TravelRequest::FIRST_VERSION);
    }

    public function test_ReconstituteRestoresEveryField(): void
    {
        $createdAt = Mother::moment(moment: Mother::NOW);
        $updatedAt = Mother::moment(moment: Mother::LATER);
        $storedVersion = 7;

        $travelRequest = TravelRequest::reconstitute(
            id: TravelRequestId::fromString(id: Mother::TRAVEL_REQUEST_ID),
            requesterId: UserId::fromString(id: Mother::REQUESTER_ID),
            destination: Destination::fromString(name: Mother::DESTINATION),
            period: Mother::period(),
            status: TravelRequestStatus::Cancelled,
            version: $storedVersion,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            decidedBy: UserId::fromString(id: Mother::ADMIN_ID),
        );

        self::assertSame(
            Mother::TRAVEL_REQUEST_ID,
            $travelRequest->id->toString(),
        );
        self::assertSame(
            Mother::REQUESTER_ID,
            $travelRequest->requesterId->toString(),
        );
        self::assertSame(
            Mother::DESTINATION,
            $travelRequest->destination->name,
        );
        self::assertEquals(
            Mother::period(),
            $travelRequest->period,
        );
        self::assertSame(
            TravelRequestStatus::Cancelled,
            $travelRequest->status,
        );
        self::assertSame(
            $storedVersion,
            $travelRequest->version,
        );
        self::assertEquals(
            $createdAt,
            $travelRequest->createdAt,
        );
        self::assertEquals(
            $updatedAt,
            $travelRequest->updatedAt,
        );
        self::assertSame(
            Mother::ADMIN_ID,
            $travelRequest->decidedBy?->toString(),
        );
        self::assertSame(
            [],
            $travelRequest->releaseEvents(),
        );
    }
}
