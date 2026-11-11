<?php

declare(strict_types=1);

namespace Tests\Infrastructure\TravelRequest\Persistence;

use Domain\TravelRequest\Enum\TravelRequestStatus;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Shared\Persistence\BinaryUuid;
use Infrastructure\TravelRequest\Persistence\EloquentTravelRequestRepository;
use Infrastructure\TravelRequest\Persistence\RequesterModel;
use Infrastructure\TravelRequest\Persistence\TravelRequestModel;
use LogicException;
use Tests\Support\LaravelTestCase;
use Tests\Support\TravelRequest\Fixture\StoresFixtureUsers;
use Tests\Support\TravelRequest\Fixture\TravelRequestMother as Mother;

final class TravelRequestRelationsTest extends LaravelTestCase
{
    use RefreshDatabase;
    use StoresFixtureUsers;

    private const string SECOND_ID = '019a6a10-aaaa-7bbb-8ccc-000000000002';

    private const string UNKNOWN_USER_ID = '019a6a10-aaaa-7bbb-8ccc-0000000000f9';

    private EloquentTravelRequestRepository $travelRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storeFixtureUsers();
        $this->travelRequests = new EloquentTravelRequestRepository();
    }

    public function test_ATravelRequestBelongsToItsRequesterAndDecider(): void
    {
        $this->travelRequests->add(travelRequest: Mother::stored(status: TravelRequestStatus::Approved));

        $travelRequestRow = TravelRequestModel::query()
            ->with(TravelRequestModel::WITH_USERS)
            ->sole();

        self::assertSame(Mother::USER_NAMES[Mother::REQUESTER_ID], $travelRequestRow->requester->name);
        self::assertSame(Mother::USER_NAMES[Mother::ADMIN_ID], $travelRequestRow->decider?->name);
    }

    public function test_AnUndecidedTravelRequestHasNoDecider(): void
    {
        $this->travelRequests->add(travelRequest: Mother::stored());

        $travelRequestRow = TravelRequestModel::query()
            ->sole();

        self::assertNull($travelRequestRow->decider);
    }

    public function test_ARequesterHasManyTravelRequests(): void
    {
        $this->travelRequests->add(travelRequest: Mother::stored());
        $this->travelRequests->add(travelRequest: Mother::stored(id: self::SECOND_ID));

        $requester = $this->requester(id: Mother::REQUESTER_ID);
        $otherUser = $this->requester(id: Mother::OTHER_USER_ID);

        self::assertCount(2, $requester->travelRequests);
        self::assertCount(0, $otherUser->travelRequests);
    }

    public function test_OwnedByKeepsOnlyTheRequestersTravelRequests(): void
    {
        $this->travelRequests->add(travelRequest: Mother::stored());
        $otherUsers = Mother::stored(id: self::SECOND_ID, requesterId: Mother::OTHER_USER_ID);
        $this->travelRequests->add(travelRequest: $otherUsers);

        $owned = TravelRequestModel::ownedBy(Mother::userId(id: Mother::OTHER_USER_ID))
            ->pluck('id')
            ->all();

        self::assertSame([self::SECOND_ID], $owned);
    }

    public function test_ATravelRequestNeedsAnExistingRequester(): void
    {
        $this->expectException(QueryException::class);

        $this->travelRequests->add(travelRequest: Mother::stored(requesterId: self::UNKNOWN_USER_ID));
    }

    public function test_AUserWithTravelRequestsCannotBeDeleted(): void
    {
        $this->travelRequests->add(travelRequest: Mother::stored());

        $this->expectException(QueryException::class);

        $this->app->make(ConnectionInterface::class)
            ->table('users')
            ->where('id', BinaryUuid::toBytes(uuid: Mother::REQUESTER_ID))
            ->delete();
    }

    public function test_ADeciderWithDecisionsCannotBeDeleted(): void
    {
        $this->travelRequests->add(travelRequest: Mother::stored(status: TravelRequestStatus::Cancelled));

        $this->expectException(QueryException::class);

        $this->app->make(ConnectionInterface::class)
            ->table('users')
            ->where('id', BinaryUuid::toBytes(uuid: Mother::ADMIN_ID))
            ->delete();
    }

    public function test_RequestersAreReadOnly(): void
    {
        $requester = $this->requester(id: Mother::REQUESTER_ID);
        $requester->name = 'Someone Else';

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('Users are written by Identity; travel requests only read them.');

        $requester->save();
    }

    public function test_RequestersCannotBeDeletedThroughTravelRequests(): void
    {
        $requester = $this->requester(id: Mother::REQUESTER_ID);

        $this->expectException(LogicException::class);

        $requester->delete();
    }

    private function requester(string $id): RequesterModel
    {
        return RequesterModel::query()
            ->whereKey(BinaryUuid::toBytes(uuid: $id))
            ->sole();
    }
}
