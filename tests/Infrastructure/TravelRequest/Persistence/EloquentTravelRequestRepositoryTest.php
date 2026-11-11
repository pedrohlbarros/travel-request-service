<?php

declare(strict_types=1);

namespace Tests\Infrastructure\TravelRequest\Persistence;

use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\Exception\TravelRequestVersionMismatch;
use Domain\TravelRequest\ValueObject\Destination;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\TravelRequest\Persistence\EloquentTravelRequestRepository;
use Tests\Support\LaravelTestCase;
use Tests\Support\TravelRequest\Fixture\StoresFixtureUsers;
use Tests\Support\TravelRequest\Fixture\TravelRequestMother as Mother;

final class EloquentTravelRequestRepositoryTest extends LaravelTestCase
{
    use RefreshDatabase;
    use StoresFixtureUsers;

    private const string NEW_DESTINATION = 'Tokyo';

    private EloquentTravelRequestRepository $travelRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storeFixtureUsers();
        $this->travelRequests = new EloquentTravelRequestRepository();
    }

    public function test_StoresAndReadsBackEveryField(): void
    {
        $approved = Mother::stored(status: TravelRequestStatus::Approved);

        $this->travelRequests->add(travelRequest: $approved);

        $found = $this->travelRequests->get(id: $approved->id);

        self::assertEquals($approved, $found);
    }

    public function test_FailsForUnknownTravelRequest(): void
    {
        $this->expectException(TravelRequestNotFound::class);

        $this->travelRequests->get(id: TravelRequestId::fromString(id: Mother::TRAVEL_REQUEST_ID));
    }

    public function test_UpdatePersistsTheChange(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());
        $travelRequest = $this->storedTravelRequest();
        $this->approve(travelRequest: $travelRequest);
        $travelRequest->releaseEvents();

        $this->travelRequests->update(travelRequest: $travelRequest, loadedVersion: TravelRequest::FIRST_VERSION);

        $stored = $this->storedTravelRequest();

        self::assertEquals($travelRequest, $stored);
    }

    public function test_UpdateWritesOnlyTheChangedColumns(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());
        $travelRequest = $this->storedTravelRequest();
        $travelRequest->changeDetails(
            editorId: Mother::userId(id: Mother::REQUESTER_ID),
            destination: Destination::fromString(name: self::NEW_DESTINATION),
            departureDate: null,
            returnDate: null,
            changedAt: Mother::moment(moment: Mother::LATER),
        );
        $database = $this->app->make(ConnectionInterface::class);
        $database->enableQueryLog();

        $this->travelRequests->update(travelRequest: $travelRequest, loadedVersion: TravelRequest::FIRST_VERSION);

        $statements = array_column($database->getQueryLog(), 'query');
        $updateStatements = array_values(array_filter(
            $statements,
            static fn(string $statement): bool => str_starts_with($statement, 'update'),
        ));

        self::assertCount(1, $updateStatements);
        self::assertStringStartsWith(
            'update `travel_requests` set `destination` = ?, `version` = ?, `updated_at` = ? where',
            $updateStatements[0],
        );
    }

    public function test_RejectsAnUpdateFromAStaleVersion(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());
        $travelRequest = $this->storedTravelRequest();
        $this->approve(travelRequest: $travelRequest);

        $this->expectException(TravelRequestVersionMismatch::class);
        $this->expectExceptionMessageIs('Travel request ' . Mother::TRAVEL_REQUEST_ID . ' is at version 1, not 2.');

        $this->travelRequests->update(travelRequest: $travelRequest, loadedVersion: TravelRequest::FIRST_VERSION + 1);
    }

    public function test_OfTwoWritersOnTheSameVersionOnlyTheFirstWins(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());
        $approval = $this->storedTravelRequest();
        $cancellation = $this->storedTravelRequest();
        $this->approve(travelRequest: $approval);
        $cancellation->cancel(
            cancellerId: Mother::userId(id: Mother::ADMIN_ID),
            cancelledAt: Mother::moment(moment: Mother::LATER),
        );
        $this->travelRequests->update(travelRequest: $approval, loadedVersion: TravelRequest::FIRST_VERSION);

        try {
            $this->travelRequests->update(travelRequest: $cancellation, loadedVersion: TravelRequest::FIRST_VERSION);
            $isSecondWriteRejected = false;
        } catch (TravelRequestVersionMismatch) {
            $isSecondWriteRejected = true;
        }

        $stored = $this->storedTravelRequest();

        self::assertTrue($isSecondWriteRejected);
        self::assertSame(TravelRequestStatus::Approved, $stored->status);
    }

    public function test_UpdateOfAMissingTravelRequestFails(): void
    {
        $this->expectException(TravelRequestNotFound::class);

        $this->travelRequests->update(travelRequest: Mother::requested(), loadedVersion: TravelRequest::FIRST_VERSION);
    }

    private function approve(TravelRequest $travelRequest): void
    {
        $travelRequest->approve(
            approverId: Mother::userId(id: Mother::ADMIN_ID),
            approvedAt: Mother::moment(moment: Mother::LATER),
        );
    }

    private function storedTravelRequest(): TravelRequest
    {
        return $this->travelRequests->get(id: TravelRequestId::fromString(id: Mother::TRAVEL_REQUEST_ID));
    }
}
