<?php

declare(strict_types=1);

namespace Tests\Application\TravelRequest\UseCase;

use Application\TravelRequest\Input\GetTravelRequestInput;
use Application\TravelRequest\UseCase\GetTravelRequestUseCase;
use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use PHPUnit\Framework\TestCase;
use Tests\Support\TravelRequest\Fixture\TravelRequestMother as Mother;
use Tests\Support\TravelRequest\Port\InMemoryTravelRequestQueries;
use Tests\Support\TravelRequest\Repository\InMemoryTravelRequestRepository;

final class GetTravelRequestUseCaseTest extends TestCase
{
    private const bool AS_ADMIN = true;

    private const bool AS_USER = false;

    private InMemoryTravelRequestRepository $travelRequests;

    private GetTravelRequestUseCase $useCase;

    protected function setUp(): void
    {
        $this->travelRequests = new InMemoryTravelRequestRepository();
        $queries = new InMemoryTravelRequestQueries(travelRequests: $this->travelRequests);
        $this->useCase = new GetTravelRequestUseCase(queries: $queries);
    }

    public function test_RequesterSeesOwnTravelRequest(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());

        $found = $this->useCase->execute(input: new GetTravelRequestInput(
            travelRequestId: Mother::TRAVEL_REQUEST_ID,
            viewerId: Mother::REQUESTER_ID,
            isViewerAdmin: self::AS_USER,
        ));

        self::assertSame(Mother::TRAVEL_REQUEST_ID, $found->id);
        self::assertEquals(Mother::userReference(id: Mother::REQUESTER_ID), $found->requester);
        self::assertSame(Mother::DESTINATION, $found->destination);
        self::assertEquals(Mother::moment(moment: Mother::DEPARTURE_DATE), $found->departureDate);
        self::assertEquals(Mother::moment(moment: Mother::RETURN_DATE), $found->returnDate);
        self::assertSame(TravelRequestStatus::Requested, $found->status);
        self::assertSame(TravelRequest::FIRST_VERSION, $found->version);
        self::assertEquals(Mother::moment(moment: Mother::NOW), $found->createdAt);
        self::assertEquals(Mother::moment(moment: Mother::NOW), $found->updatedAt);
        self::assertNull($found->decidedBy);
    }

    public function test_ViewShowsWhoDecided(): void
    {
        $this->travelRequests->add(travelRequest: Mother::approved());

        $found = $this->useCase->execute(input: new GetTravelRequestInput(
            travelRequestId: Mother::TRAVEL_REQUEST_ID,
            viewerId: Mother::REQUESTER_ID,
            isViewerAdmin: self::AS_USER,
        ));

        self::assertSame(TravelRequestStatus::Approved, $found->status);
        self::assertEquals(Mother::userReference(id: Mother::ADMIN_ID), $found->decidedBy);
        self::assertEquals(Mother::moment(moment: Mother::LATER), $found->updatedAt);
    }

    public function test_AdminSeesAnyTravelRequest(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());

        $found = $this->useCase->execute(input: new GetTravelRequestInput(
            travelRequestId: Mother::TRAVEL_REQUEST_ID,
            viewerId: Mother::ADMIN_ID,
            isViewerAdmin: self::AS_ADMIN,
        ));

        self::assertSame(Mother::TRAVEL_REQUEST_ID, $found->id);
    }

    public function test_UserCannotSeeSomeoneElsesTravelRequest(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());

        $this->expectException(TravelRequestNotFound::class);

        $this->useCase->execute(input: new GetTravelRequestInput(
            travelRequestId: Mother::TRAVEL_REQUEST_ID,
            viewerId: Mother::OTHER_USER_ID,
            isViewerAdmin: self::AS_USER,
        ));
    }

    public function test_FailsForUnknownTravelRequest(): void
    {
        $this->expectException(TravelRequestNotFound::class);

        $this->useCase->execute(input: new GetTravelRequestInput(
            travelRequestId: Mother::TRAVEL_REQUEST_ID,
            viewerId: Mother::ADMIN_ID,
            isViewerAdmin: self::AS_ADMIN,
        ));
    }
}
