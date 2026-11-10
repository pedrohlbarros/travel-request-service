<?php

declare(strict_types=1);

namespace Tests\Application\TravelRequest\UseCase;

use Application\TravelRequest\Input\ChangeTravelRequestDetailsInput;
use Application\TravelRequest\Output\TravelRequestOutput;
use Application\TravelRequest\UseCase\ChangeTravelRequestDetailsUseCase;
use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Exception\TravelRequestNotEditable;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\Exception\TravelRequestNotOwned;
use Domain\TravelRequest\Exception\TravelRequestVersionMismatch;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use PHPUnit\Framework\TestCase;
use Tests\Support\Shared\Port\FrozenClock;
use Tests\Support\TravelRequest\Fixture\TravelRequestMother as Mother;
use Tests\Support\TravelRequest\Repository\InMemoryTravelRequestRepository;

final class ChangeTravelRequestDetailsUseCaseTest extends TestCase
{
    private const string NEW_DESTINATION = 'Tokyo';

    private const string NEW_DEPARTURE_DATE = '2026-12-10';

    private const string NEW_RETURN_DATE = '2026-12-20';

    private InMemoryTravelRequestRepository $travelRequests;

    private ChangeTravelRequestDetailsUseCase $useCase;

    protected function setUp(): void
    {
        $clock = new FrozenClock(frozenAt: Mother::moment(moment: Mother::LATER));
        $this->travelRequests = new InMemoryTravelRequestRepository();
        $this->useCase = new ChangeTravelRequestDetailsUseCase(travelRequests: $this->travelRequests, clock: $clock);
    }

    public function test_RequesterChangesDestinationAndDates(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());

        $changed = $this->useCase->execute(input: $this->changeBy(
            editorId: Mother::REQUESTER_ID,
            expectedVersion: TravelRequest::FIRST_VERSION,
        ));

        $stored = $this->storedTravelRequest();

        self::assertEquals(TravelRequestOutput::fromEntity(travelRequest: $stored), $changed);
        self::assertSame(self::NEW_DESTINATION, $stored->destination->name);
        self::assertEquals(
            Mother::period(departureDate: self::NEW_DEPARTURE_DATE, returnDate: self::NEW_RETURN_DATE),
            $stored->period,
        );
        self::assertSame(TravelRequest::FIRST_VERSION + 1, $stored->version);
        self::assertEquals(Mother::moment(moment: Mother::LATER), $stored->updatedAt);
    }

    public function test_RejectsStaleExpectedVersion(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());
        $this->useCase->execute(input: $this->changeBy(
            editorId: Mother::REQUESTER_ID,
            expectedVersion: TravelRequest::FIRST_VERSION,
        ));

        $this->expectException(TravelRequestVersionMismatch::class);

        $this->useCase->execute(input: $this->changeBy(
            editorId: Mother::REQUESTER_ID,
            expectedVersion: TravelRequest::FIRST_VERSION,
        ));
    }

    public function test_OtherUserCannotChangeDetails(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());

        $this->expectException(TravelRequestNotOwned::class);

        $this->useCase->execute(input: $this->changeBy(
            editorId: Mother::OTHER_USER_ID,
            expectedVersion: TravelRequest::FIRST_VERSION,
        ));
    }

    public function test_AdminCannotChangeDetailsOfSomeoneElsesRequest(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());

        $this->expectException(TravelRequestNotOwned::class);

        $this->useCase->execute(input: $this->changeBy(
            editorId: Mother::ADMIN_ID,
            expectedVersion: TravelRequest::FIRST_VERSION,
        ));
    }

    public function test_CannotChangeDetailsOfApprovedTravelRequest(): void
    {
        $approved = Mother::approved();
        $this->travelRequests->add(travelRequest: $approved);

        $this->expectException(TravelRequestNotEditable::class);

        $this->useCase->execute(input: $this->changeBy(
            editorId: Mother::REQUESTER_ID,
            expectedVersion: $approved->version,
        ));
    }

    public function test_FailsForUnknownTravelRequest(): void
    {
        $this->expectException(TravelRequestNotFound::class);

        $this->useCase->execute(input: $this->changeBy(
            editorId: Mother::REQUESTER_ID,
            expectedVersion: TravelRequest::FIRST_VERSION,
        ));
    }

    private function changeBy(string $editorId, int $expectedVersion): ChangeTravelRequestDetailsInput
    {
        return new ChangeTravelRequestDetailsInput(
            travelRequestId: Mother::TRAVEL_REQUEST_ID,
            editorId: $editorId,
            expectedVersion: $expectedVersion,
            destination: self::NEW_DESTINATION,
            departureDate: Mother::moment(moment: self::NEW_DEPARTURE_DATE),
            returnDate: Mother::moment(moment: self::NEW_RETURN_DATE),
        );
    }

    private function storedTravelRequest(): TravelRequest
    {
        return $this->travelRequests->get(id: TravelRequestId::fromString(id: Mother::TRAVEL_REQUEST_ID));
    }
}
