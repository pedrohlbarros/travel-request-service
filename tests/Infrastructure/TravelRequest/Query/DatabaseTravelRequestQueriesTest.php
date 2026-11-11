<?php

declare(strict_types=1);

namespace Tests\Infrastructure\TravelRequest\Query;

use Application\TravelRequest\Exception\InvalidListCursor;
use Application\TravelRequest\Input\TravelRequestFilters;
use Application\TravelRequest\Output\TravelRequestOutput;
use Application\TravelRequest\Output\TravelRequestPage;
use Application\TravelRequest\Port\TravelRequestListCriteria;
use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\TravelRequest\Persistence\EloquentTravelRequestRepository;
use Infrastructure\TravelRequest\Query\DatabaseTravelRequestQueries;
use Infrastructure\TravelRequest\Query\ListCursor;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\LaravelTestCase;
use Tests\Support\TravelRequest\Fixture\StoresFixtureUsers;
use Tests\Support\TravelRequest\Fixture\TravelRequestMother as Mother;

final class DatabaseTravelRequestQueriesTest extends LaravelTestCase
{
    use RefreshDatabase;
    use StoresFixtureUsers;

    private const string FIRST_ID = '019a6a10-aaaa-7bbb-8ccc-000000000001';

    private const string SECOND_ID = '019a6a10-aaaa-7bbb-8ccc-000000000002';

    private const string THIRD_ID = '019a6a10-aaaa-7bbb-8ccc-000000000003';

    private const string FOURTH_ID = '019a6a10-aaaa-7bbb-8ccc-000000000004';

    private const string FIFTH_ID = '019a6a10-aaaa-7bbb-8ccc-000000000005';

    private const int PER_PAGE = 20;

    private DatabaseTravelRequestQueries $queries;

    private EloquentTravelRequestRepository $travelRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storeFixtureUsers();
        $this->queries = new DatabaseTravelRequestQueries();
        $this->travelRequests = new EloquentTravelRequestRepository();
    }

    public function test_FindsATravelRequestAsItsOutput(): void
    {
        $approved = Mother::stored(status: TravelRequestStatus::Approved);
        $this->add(travelRequest: $approved);

        $found = $this->queries->find(id: $approved->id);

        self::assertEquals(Mother::output(travelRequest: $approved), $found);
    }

    public function test_ListLoadsTheUsersOnceForTheWholePage(): void
    {
        $this->add(travelRequest: Mother::stored(id: self::FIRST_ID, status: TravelRequestStatus::Approved));
        $this->add(travelRequest: Mother::stored(id: self::SECOND_ID, requesterId: Mother::OTHER_USER_ID));
        $this->add(travelRequest: Mother::stored(id: self::THIRD_ID, status: TravelRequestStatus::Cancelled));
        $database = $this->app->make(ConnectionInterface::class);
        $database->enableQueryLog();

        $page = $this->list(filters: new TravelRequestFilters());

        $namesOnThePage = array_map(
            static fn(TravelRequestOutput $travelRequest): string => $travelRequest->requester->name,
            $page->travelRequests,
        );

        self::assertCount(3, $database->getQueryLog());
        self::assertSame(
            [Mother::USER_NAMES[Mother::REQUESTER_ID], Mother::USER_NAMES[Mother::OTHER_USER_ID], Mother::USER_NAMES[Mother::REQUESTER_ID]],
            $namesOnThePage,
        );
        self::assertEquals(Mother::userReference(id: Mother::ADMIN_ID), $page->travelRequests[0]->decidedBy);
    }

    public function test_GetFailsForAnUnknownId(): void
    {
        $this->expectException(TravelRequestNotFound::class);

        $this->queries->get(id: TravelRequestId::fromString(id: self::FIRST_ID));
    }

    public function test_GetReturnsWhatFindReturns(): void
    {
        $this->add(travelRequest: Mother::stored());

        $travelRequest = $this->queries->get(id: TravelRequestId::fromString(id: Mother::TRAVEL_REQUEST_ID));

        $expected = Mother::output(travelRequest: Mother::stored());

        self::assertEquals($expected, $travelRequest);
    }

    public function test_FindsNothingForAnUnknownId(): void
    {
        $found = $this->queries->find(id: TravelRequestId::fromString(id: self::FIRST_ID));

        self::assertNull($found);
    }

    public function test_ListsNewestFirstAndScopesByRequester(): void
    {
        $this->add(travelRequest: Mother::stored(id: self::FIRST_ID));
        $this->add(travelRequest: Mother::stored(id: self::SECOND_ID, requesterId: Mother::OTHER_USER_ID));
        $this->add(travelRequest: Mother::stored(id: self::THIRD_ID));

        $everyone = $this->list(filters: new TravelRequestFilters());
        $ownOnly = $this->list(filters: new TravelRequestFilters(), requesterId: Mother::REQUESTER_ID);

        self::assertSame([self::THIRD_ID, self::SECOND_ID, self::FIRST_ID], $this->idsOf(page: $everyone));
        self::assertSame([self::THIRD_ID, self::FIRST_ID], $this->idsOf(page: $ownOnly));
    }

    public function test_FiltersByAnyOfTheGivenStatuses(): void
    {
        $this->add(travelRequest: Mother::stored(id: self::FIRST_ID));
        $this->add(travelRequest: Mother::stored(id: self::SECOND_ID, status: TravelRequestStatus::Approved));
        $this->add(travelRequest: Mother::stored(id: self::THIRD_ID, status: TravelRequestStatus::Cancelled));
        $decided = new TravelRequestFilters(statuses: [TravelRequestStatus::Approved, TravelRequestStatus::Cancelled]);

        $page = $this->list(filters: $decided);

        self::assertSame([self::THIRD_ID, self::SECOND_ID], $this->idsOf(page: $page));
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function destinationSearches(): array
    {
        return [
            'containedIgnoringCase' => ['PAUL', [self::FIRST_ID]],
            'percentIsLiteral' => ['100%', [self::SECOND_ID]],
            'underscoreIsLiteral' => ['a_b', [self::THIRD_ID]],
            'noMatch' => ['Tokyo', []],
        ];
    }

    /**
     * @param list<string> $expectedIds
     */
    #[DataProvider('destinationSearches')]
    public function test_FiltersByDestinationContainingTheText(string $search, array $expectedIds): void
    {
        $this->add(travelRequest: Mother::stored(id: self::FIRST_ID, destination: 'São Paulo'));
        $this->add(travelRequest: Mother::stored(id: self::SECOND_ID, destination: 'Resort 100% fun'));
        $this->add(travelRequest: Mother::stored(id: self::THIRD_ID, destination: 'Spa a_b'));
        $this->add(travelRequest: Mother::stored(id: self::FOURTH_ID, destination: 'Resort 1000 fun, aXb'));

        $page = $this->list(filters: new TravelRequestFilters(destination: $search));

        self::assertSame($expectedIds, $this->idsOf(page: $page));
    }

    public function test_FiltersByCreationDaysInclusively(): void
    {
        $this->add(travelRequest: Mother::stored(id: self::FIRST_ID, createdAt: '2026-11-01 23:59:59'));
        $this->add(travelRequest: Mother::stored(id: self::SECOND_ID, createdAt: '2026-11-02 00:00:00'));
        $this->add(travelRequest: Mother::stored(id: self::THIRD_ID, createdAt: '2026-11-03 23:59:59'));
        $this->add(travelRequest: Mother::stored(id: self::FOURTH_ID, createdAt: '2026-11-04 00:00:00'));
        $secondToThirdDay = new TravelRequestFilters(
            createdFrom: Mother::moment(moment: '2026-11-02'),
            createdTo: Mother::moment(moment: '2026-11-03'),
        );

        $page = $this->list(filters: $secondToThirdDay);

        self::assertSame([self::THIRD_ID, self::SECOND_ID], $this->idsOf(page: $page));
    }

    public function test_FiltersTripsThatOverlapTheTravelRange(): void
    {
        $this->addTrip(id: self::FIRST_ID, departureDate: '2026-12-01', returnDate: '2026-12-09');
        $this->addTrip(id: self::SECOND_ID, departureDate: '2026-12-10', returnDate: '2026-12-12');
        $this->addTrip(id: self::THIRD_ID, departureDate: '2026-12-15', returnDate: '2026-12-25');
        $this->addTrip(id: self::FOURTH_ID, departureDate: '2026-12-20', returnDate: '2026-12-21');
        $this->addTrip(id: self::FIFTH_ID, departureDate: '2026-12-21', returnDate: '2026-12-30');
        $tenthToTwentieth = new TravelRequestFilters(
            travelFrom: Mother::moment(moment: '2026-12-10'),
            travelTo: Mother::moment(moment: '2026-12-20'),
        );

        $page = $this->list(filters: $tenthToTwentieth);

        self::assertSame([self::FOURTH_ID, self::THIRD_ID, self::SECOND_ID], $this->idsOf(page: $page));
    }

    public function test_EachTravelBoundWorksAlone(): void
    {
        $this->addTrip(id: self::FIRST_ID, departureDate: '2026-12-01', returnDate: '2026-12-05');
        $this->addTrip(id: self::SECOND_ID, departureDate: '2026-12-10', returnDate: '2026-12-15');

        $fromTheSixth = new TravelRequestFilters(travelFrom: Mother::moment(moment: '2026-12-06'));
        $upToTheNinth = new TravelRequestFilters(travelTo: Mother::moment(moment: '2026-12-09'));

        $endingFromTheSixth = $this->list(filters: $fromTheSixth);
        $startingUpToTheNinth = $this->list(filters: $upToTheNinth);

        self::assertSame([self::SECOND_ID], $this->idsOf(page: $endingFromTheSixth));
        self::assertSame([self::FIRST_ID], $this->idsOf(page: $startingUpToTheNinth));
    }

    public function test_WalksPagesForwardAndBack(): void
    {
        foreach ([self::FIRST_ID, self::SECOND_ID, self::THIRD_ID, self::FOURTH_ID, self::FIFTH_ID] as $id) {
            $this->add(travelRequest: Mother::stored(id: $id));
        }

        $noFilters = new TravelRequestFilters();

        $firstPage = $this->list(filters: $noFilters, perPage: 2);
        $secondPage = $this->list(filters: $noFilters, perPage: 2, cursor: $firstPage->nextCursor);
        $lastPage = $this->list(filters: $noFilters, perPage: 2, cursor: $secondPage->nextCursor);
        $backToSecond = $this->list(filters: $noFilters, perPage: 2, cursor: $lastPage->previousCursor);
        $backToFirst = $this->list(filters: $noFilters, perPage: 2, cursor: $backToSecond->previousCursor);

        self::assertSame([self::FIFTH_ID, self::FOURTH_ID], $this->idsOf(page: $firstPage));
        self::assertNull($firstPage->previousCursor);
        self::assertSame([self::THIRD_ID, self::SECOND_ID], $this->idsOf(page: $secondPage));
        self::assertSame([self::FIRST_ID], $this->idsOf(page: $lastPage));
        self::assertNull($lastPage->nextCursor);
        self::assertSame([self::THIRD_ID, self::SECOND_ID], $this->idsOf(page: $backToSecond));
        self::assertNotNull($backToSecond->nextCursor);
        self::assertSame([self::FIFTH_ID, self::FOURTH_ID], $this->idsOf(page: $backToFirst));
        self::assertNull($backToFirst->previousCursor);
        self::assertNotNull($backToFirst->nextCursor);
        self::assertSame(2, $firstPage->perPage);
    }

    public function test_AnExactlyFullPageHasNoNextCursor(): void
    {
        $this->add(travelRequest: Mother::stored(id: self::FIRST_ID));
        $this->add(travelRequest: Mother::stored(id: self::SECOND_ID));

        $page = $this->list(filters: new TravelRequestFilters(), perPage: 2);

        self::assertSame([self::SECOND_ID, self::FIRST_ID], $this->idsOf(page: $page));
        self::assertNull($page->nextCursor);
    }

    public function test_RejectsACursorItDidNotIssue(): void
    {
        $this->expectException(InvalidListCursor::class);

        $this->list(filters: new TravelRequestFilters(), cursor: 'not-a-cursor');
    }

    public function test_ACursorPastTheEndGivesAnEmptyPage(): void
    {
        $this->add(travelRequest: Mother::stored(id: self::SECOND_ID));
        $olderThanAll = ListCursor::olderThan(edgeId: self::FIRST_ID);

        $page = $this->list(filters: new TravelRequestFilters(), cursor: $olderThanAll->encode());

        self::assertSame([], $page->travelRequests);
        self::assertNull($page->nextCursor);
        self::assertNull($page->previousCursor);
    }

    private function add(TravelRequest $travelRequest): void
    {
        $this->travelRequests->add(travelRequest: $travelRequest);
    }

    private function addTrip(string $id, string $departureDate, string $returnDate): void
    {
        $trip = Mother::stored(id: $id, departureDate: $departureDate, returnDate: $returnDate);

        $this->travelRequests->add(travelRequest: $trip);
    }

    /**
     * @param positive-int $perPage
     */
    private function list(
        TravelRequestFilters $filters,
        ?string $requesterId = null,
        int $perPage = self::PER_PAGE,
        ?string $cursor = null,
    ): TravelRequestPage {
        $criteria = new TravelRequestListCriteria(
            requesterId: $requesterId === null ? null : Mother::userId(id: $requesterId),
            filters: $filters,
            cursor: $cursor,
            perPage: $perPage,
        );

        return $this->queries->list(criteria: $criteria);
    }

    /**
     * @return list<string>
     */
    private function idsOf(TravelRequestPage $page): array
    {
        return array_map(
            static fn(TravelRequestOutput $travelRequest): string => $travelRequest->id,
            $page->travelRequests,
        );
    }
}
