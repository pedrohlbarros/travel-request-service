<?php

declare(strict_types=1);

namespace Infrastructure\TravelRequest\Cache;

use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Repository\TravelRequestRepository;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\DatabaseTransactionsManager;

/**
 * Every write goes through the repository, so this is the one place that drops what the read cache holds.
 * It drops it after the commit: before that, a concurrent read would cache the old row again.
 */
final readonly class CacheInvalidatingTravelRequestRepository implements TravelRequestRepository
{
    public function __construct(
        private TravelRequestRepository $travelRequests,
        private Cache $cache,
        private DatabaseTransactionsManager $transactions,
    ) {}

    public function get(TravelRequestId $id): TravelRequest
    {
        return $this->travelRequests->get(id: $id);
    }

    public function add(TravelRequest $travelRequest): void
    {
        $this->travelRequests->add(travelRequest: $travelRequest);
        $this->invalidate(travelRequest: $travelRequest);
    }

    public function update(TravelRequest $travelRequest, int $loadedVersion): void
    {
        $this->travelRequests->update(travelRequest: $travelRequest, loadedVersion: $loadedVersion);
        $this->invalidate(travelRequest: $travelRequest);
    }

    private function invalidate(TravelRequest $travelRequest): void
    {
        $this->transactions->addCallback(function () use ($travelRequest): void {
            $this->forget(travelRequest: $travelRequest);
        });
    }

    private function forget(TravelRequest $travelRequest): void
    {
        $this->cache->forget(TravelRequestCacheKeys::detail(id: $travelRequest->id));

        foreach (TravelRequestCacheKeys::generationsSeeing(requesterId: $travelRequest->requesterId) as $generationKey) {
            $this->cache->increment($generationKey);
        }
    }
}
