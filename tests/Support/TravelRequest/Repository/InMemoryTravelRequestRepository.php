<?php

declare(strict_types=1);

namespace Tests\Support\TravelRequest\Repository;

use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\Exception\TravelRequestVersionMismatch;
use Domain\TravelRequest\Repository\TravelRequestRepository;
use Domain\TravelRequest\ValueObject\TravelRequestId;

/**
 * Keeps copies instead of the given instances, so a change only becomes visible after add() or update(),
 * like a real database.
 */
final class InMemoryTravelRequestRepository implements TravelRequestRepository
{
    /** @var array<string, TravelRequest> */
    private array $storedTravelRequestById = [];

    public function get(TravelRequestId $id): TravelRequest
    {
        $storedTravelRequest = $this->storedTravelRequestById[$id->toString()] ?? null;

        if ($storedTravelRequest === null) {
            throw TravelRequestNotFound::withId(id: $id);
        }

        return clone $storedTravelRequest;
    }

    public function add(TravelRequest $travelRequest): void
    {
        $this->store(travelRequest: $travelRequest);
    }

    public function update(TravelRequest $travelRequest, int $loadedVersion): void
    {
        $storedVersion = $this->get(id: $travelRequest->id)->version;

        if ($storedVersion !== $loadedVersion) {
            throw TravelRequestVersionMismatch::expected(
                id: $travelRequest->id,
                expectedVersion: $loadedVersion,
                currentVersion: $storedVersion,
            );
        }

        $this->store(travelRequest: $travelRequest);
    }

    private function store(TravelRequest $travelRequest): void
    {
        $storedCopy = clone $travelRequest;
        $storedCopy->releaseEvents();

        $this->storedTravelRequestById[$travelRequest->id->toString()] = $storedCopy;
    }
}
