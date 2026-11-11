<?php

declare(strict_types=1);

namespace Infrastructure\TravelRequest\Cache;

use Application\TravelRequest\Output\TravelRequestOutput;
use Application\TravelRequest\Output\TravelRequestPage;
use Application\TravelRequest\Port\TravelRequestListCriteria;
use Application\TravelRequest\Port\TravelRequestQueries;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Connection;

/**
 * Misses are not cached, so a request is readable as soon as it is created. User names are cached with the
 * request: Identity offers no way to rename a user, so nothing has to drop them.
 *
 * A miss reads from the primary, never the replica: the cache is dropped right after a commit, and a lagging
 * replica would put the old state back in it until the entry expires.
 */
final readonly class CachedTravelRequestQueries implements TravelRequestQueries
{
    private const int FIRST_GENERATION = 0;

    public function __construct(
        private TravelRequestQueries $queries,
        private Cache $cache,
        private TravelRequestCacheSettings $settings,
        private Connection $primary,
    ) {}

    public function find(TravelRequestId $id): ?TravelRequestOutput
    {
        $detailKey = TravelRequestCacheKeys::detail(id: $id);
        $cachedTravelRequest = $this->cache->get($detailKey);

        if ($cachedTravelRequest instanceof TravelRequestOutput) {
            return $cachedTravelRequest;
        }

        $travelRequest = $this->primary->usingWritePdo(fn(): ?TravelRequestOutput => $this->queries->find(id: $id));

        if ($travelRequest !== null) {
            $this->cache->put($detailKey, $travelRequest, $this->settings->detailSeconds);
        }

        return $travelRequest;
    }

    public function get(TravelRequestId $id): TravelRequestOutput
    {
        $travelRequest = $this->find(id: $id);

        if ($travelRequest === null) {
            throw TravelRequestNotFound::withId(id: $id);
        }

        return $travelRequest;
    }

    public function list(TravelRequestListCriteria $criteria): TravelRequestPage
    {
        $generationKey = TravelRequestCacheKeys::listGeneration(requesterId: $criteria->requesterId);
        $storedGeneration = $this->cache->get($generationKey, self::FIRST_GENERATION);
        $generation = is_numeric($storedGeneration) ? (int) $storedGeneration : self::FIRST_GENERATION;
        $listKey = TravelRequestCacheKeys::list(criteria: $criteria, generation: $generation);
        $cachedPage = $this->cache->get($listKey);

        if ($cachedPage instanceof TravelRequestPage) {
            return $cachedPage;
        }

        $page = $this->primary->usingWritePdo(fn(): TravelRequestPage => $this->queries->list(criteria: $criteria));
        $this->cache->put($listKey, $page, $this->settings->listSeconds);

        return $page;
    }
}
