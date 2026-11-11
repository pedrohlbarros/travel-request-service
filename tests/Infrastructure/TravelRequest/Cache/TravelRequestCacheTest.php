<?php

declare(strict_types=1);

namespace Tests\Infrastructure\TravelRequest\Cache;

use Application\TravelRequest\Input\TravelRequestFilters;
use Application\TravelRequest\Output\TravelRequestOutput;
use Application\TravelRequest\Output\TravelRequestPage;
use Application\TravelRequest\Port\TravelRequestListCriteria;
use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\TravelRequest\Cache\CachedTravelRequestQueries;
use Infrastructure\TravelRequest\Cache\CacheInvalidatingTravelRequestRepository;
use Infrastructure\TravelRequest\Cache\TravelRequestCacheSettings;
use Infrastructure\TravelRequest\Persistence\EloquentTravelRequestRepository;
use Infrastructure\TravelRequest\Persistence\TravelRequestModel;
use Infrastructure\TravelRequest\Query\DatabaseTravelRequestQueries;
use Tests\Support\LaravelTestCase;
use Tests\Support\TravelRequest\Fixture\StoresFixtureUsers;
use Tests\Support\TravelRequest\Fixture\TravelRequestMother as Mother;

/**
 * Runs against the real Redis store, so what is cached must also survive serialization.
 */
final class TravelRequestCacheTest extends LaravelTestCase
{
    use RefreshDatabase;
    use StoresFixtureUsers;

    private const string REDIS_STORE = 'redis';

    private const string SECOND_ID = '019a6a10-aaaa-7bbb-8ccc-000000000002';

    private const string THIRD_ID = '019a6a10-aaaa-7bbb-8ccc-000000000003';

    private const int PER_PAGE = 20;

    private const int TTL_SECONDS = 60;

    private EloquentTravelRequestRepository $uncachedTravelRequests;

    private CacheInvalidatingTravelRequestRepository $travelRequests;

    private CachedTravelRequestQueries $queries;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storeFixtureUsers();
        $cache = $this->redisCache();
        $cache->clear();
        $this->uncachedTravelRequests = new EloquentTravelRequestRepository();
        $this->travelRequests = new CacheInvalidatingTravelRequestRepository(
            travelRequests: $this->uncachedTravelRequests,
            cache: $cache,
            transactions: $this->app->make('db.transactions'),
        );
        $this->queries = new CachedTravelRequestQueries(
            queries: new DatabaseTravelRequestQueries(),
            cache: $cache,
            settings: new TravelRequestCacheSettings(detailSeconds: self::TTL_SECONDS, listSeconds: self::TTL_SECONDS),
        );
    }

    public function test_DetailIsServedFromTheCacheOnceRead(): void
    {
        $approved = Mother::stored(status: TravelRequestStatus::Approved);
        $this->travelRequests->add(travelRequest: $approved);
        $this->queries->find(id: $approved->id);
        $this->deleteRowsBehindTheCache();

        $cached = $this->queries->find(id: $approved->id);

        self::assertEquals(Mother::output(travelRequest: $approved), $cached);
    }

    public function test_GetIsServedFromTheCacheToo(): void
    {
        $this->travelRequests->add(travelRequest: Mother::stored());
        $id = TravelRequestId::fromString(id: Mother::TRAVEL_REQUEST_ID);
        $this->queries->get(id: $id);
        $this->deleteRowsBehindTheCache();

        $cached = $this->queries->get(id: $id);

        self::assertSame(Mother::TRAVEL_REQUEST_ID, $cached->id);
    }

    public function test_GetFailsForAnUnknownId(): void
    {
        $this->expectException(TravelRequestNotFound::class);

        $this->queries->get(id: TravelRequestId::fromString(id: Mother::TRAVEL_REQUEST_ID));
    }

    public function test_AMissIsNotCached(): void
    {
        $id = TravelRequestId::fromString(id: Mother::TRAVEL_REQUEST_ID);
        $this->queries->find(id: $id);
        $this->uncachedTravelRequests->add(travelRequest: Mother::requested());

        $found = $this->queries->find(id: $id);

        self::assertNotNull($found);
    }

    public function test_UpdateDropsTheCachedDetail(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());
        $this->queries->find(id: TravelRequestId::fromString(id: Mother::TRAVEL_REQUEST_ID));
        $travelRequest = $this->approvedFromStorage();

        $this->travelRequests->update(travelRequest: $travelRequest, loadedVersion: TravelRequest::FIRST_VERSION);

        $found = $this->queries->find(id: $travelRequest->id);

        self::assertSame(TravelRequestStatus::Approved, $found?->status);
    }

    public function test_InsideATransactionTheCacheIsDroppedOnlyAtTheCommit(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());
        $id = TravelRequestId::fromString(id: Mother::TRAVEL_REQUEST_ID);
        $this->queries->find(id: $id);
        $travelRequest = $this->approvedFromStorage();
        $statusSeenBeforeTheCommit = null;

        $this->app->make(ConnectionInterface::class)
            ->transaction(function () use ($travelRequest, $id, &$statusSeenBeforeTheCommit): void {
                $this->travelRequests->update(
                    travelRequest: $travelRequest,
                    loadedVersion: TravelRequest::FIRST_VERSION,
                );
                $statusSeenBeforeTheCommit = $this->queries->find(id: $id)?->status;
            });

        $statusAfterTheCommit = $this->queries->find(id: $id)?->status;

        self::assertSame(TravelRequestStatus::Requested, $statusSeenBeforeTheCommit);
        self::assertSame(TravelRequestStatus::Approved, $statusAfterTheCommit);
    }

    public function test_ListIsServedFromTheCacheUntilAWriteItCanSee(): void
    {
        $this->travelRequests->add(travelRequest: Mother::stored());
        $this->listFor(requesterId: Mother::REQUESTER_ID);
        $this->uncachedTravelRequests->add(travelRequest: Mother::stored(id: self::SECOND_ID));

        $cachedPage = $this->listFor(requesterId: Mother::REQUESTER_ID);
        $this->travelRequests->add(travelRequest: Mother::stored(id: self::THIRD_ID));
        $freshPage = $this->listFor(requesterId: Mother::REQUESTER_ID);

        self::assertSame([Mother::TRAVEL_REQUEST_ID], $this->idsOf(page: $cachedPage));
        self::assertSame([self::THIRD_ID, self::SECOND_ID, Mother::TRAVEL_REQUEST_ID], $this->idsOf(page: $freshPage));
    }

    public function test_AWriteRefreshesTheAdminListToo(): void
    {
        $this->listFor(requesterId: null);

        $this->travelRequests->add(travelRequest: Mother::stored());

        $adminPage = $this->listFor(requesterId: null);

        self::assertSame([Mother::TRAVEL_REQUEST_ID], $this->idsOf(page: $adminPage));
    }

    public function test_AWriteLeavesOtherRequestersListsCached(): void
    {
        $this->listFor(requesterId: Mother::OTHER_USER_ID);
        $otherRequesters = Mother::stored(id: self::SECOND_ID, requesterId: Mother::OTHER_USER_ID);
        $this->uncachedTravelRequests->add(travelRequest: $otherRequesters);

        $this->travelRequests->add(travelRequest: Mother::stored());

        $otherRequestersPage = $this->listFor(requesterId: Mother::OTHER_USER_ID);

        self::assertSame([], $this->idsOf(page: $otherRequestersPage));
    }

    private function redisCache(): Cache
    {
        return $this->app->make(CacheManager::class)
            ->store(self::REDIS_STORE);
    }

    private function deleteRowsBehindTheCache(): void
    {
        TravelRequestModel::query()
            ->delete();
    }

    private function approvedFromStorage(): TravelRequest
    {
        $travelRequest = $this->uncachedTravelRequests->get(id: TravelRequestId::fromString(id: Mother::TRAVEL_REQUEST_ID));
        $travelRequest->approve(
            approverId: Mother::userId(id: Mother::ADMIN_ID),
            approvedAt: Mother::moment(moment: Mother::LATER),
        );

        return $travelRequest;
    }

    private function listFor(?string $requesterId): TravelRequestPage
    {
        $criteria = new TravelRequestListCriteria(
            requesterId: $requesterId === null ? null : Mother::userId(id: $requesterId),
            filters: new TravelRequestFilters(),
            cursor: null,
            perPage: self::PER_PAGE,
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
