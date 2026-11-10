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
        $this->travelRequests = new InMemoryTravelRequestRepository();
        $this->useCase = new ChangeTravelRequestDetailsUseCase(
            $this->travelRequests,
            new FrozenClock(Mother::moment(Mother::LATER)),
        );
    }

    public function test_RequesterChangesDestinationAndDates(): void
    {
        $this->travelRequests->add(Mother::requested());

        $changed = $this->useCase->execute($this->changeBy(Mother::REQUESTER_ID, TravelRequest::FIRST_VERSION));

        $stored = $this->storedTravelRequest();
        self::assertEquals(TravelRequestOutput::fromEntity($stored), $changed);
        self::assertSame(self::NEW_DESTINATION, $stored->destination->name);
        self::assertEquals(Mother::period(self::NEW_DEPARTURE_DATE, self::NEW_RETURN_DATE), $stored->period);
        self::assertSame(TravelRequest::FIRST_VERSION + 1, $stored->version);
        self::assertEquals(Mother::moment(Mother::LATER), $stored->updatedAt);
    }

    public function test_RejectsStaleExpectedVersion(): void
    {
        $this->travelRequests->add(Mother::requested());
        $this->useCase->execute($this->changeBy(Mother::REQUESTER_ID, TravelRequest::FIRST_VERSION));

        $this->expectException(TravelRequestVersionMismatch::class);

        $this->useCase->execute($this->changeBy(Mother::REQUESTER_ID, TravelRequest::FIRST_VERSION));
    }

    public function test_OtherUserCannotChangeDetails(): void
    {
        $this->travelRequests->add(Mother::requested());

        $this->expectException(TravelRequestNotOwned::class);

        $this->useCase->execute($this->changeBy(Mother::OTHER_USER_ID, TravelRequest::FIRST_VERSION));
    }

    public function test_AdminCannotChangeDetailsOfSomeoneElsesRequest(): void
    {
        $this->travelRequests->add(Mother::requested());

        $this->expectException(TravelRequestNotOwned::class);

        $this->useCase->execute($this->changeBy(Mother::ADMIN_ID, TravelRequest::FIRST_VERSION));
    }

    public function test_CannotChangeDetailsOfApprovedTravelRequest(): void
    {
        $approved = Mother::approved();
        $this->travelRequests->add($approved);

        $this->expectException(TravelRequestNotEditable::class);

        $this->useCase->execute($this->changeBy(Mother::REQUESTER_ID, $approved->version));
    }

    public function test_FailsForUnknownTravelRequest(): void
    {
        $this->expectException(TravelRequestNotFound::class);

        $this->useCase->execute($this->changeBy(Mother::REQUESTER_ID, TravelRequest::FIRST_VERSION));
    }

    private function changeBy(string $editorId, int $expectedVersion): ChangeTravelRequestDetailsInput
    {
        return new ChangeTravelRequestDetailsInput(
            Mother::TRAVEL_REQUEST_ID,
            $editorId,
            $expectedVersion,
            self::NEW_DESTINATION,
            Mother::moment(self::NEW_DEPARTURE_DATE),
            Mother::moment(self::NEW_RETURN_DATE),
        );
    }

    private function storedTravelRequest(): TravelRequest
    {
        return $this->travelRequests->get(TravelRequestId::fromString(Mother::TRAVEL_REQUEST_ID));
    }
}
