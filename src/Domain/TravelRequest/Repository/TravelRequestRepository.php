<?php

declare(strict_types=1);

namespace Domain\TravelRequest\Repository;

use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\Exception\TravelRequestVersionMismatch;
use Domain\TravelRequest\ValueObject\TravelRequestId;

interface TravelRequestRepository
{
    /**
     * @throws TravelRequestNotFound
     */
    public function get(TravelRequestId $id): TravelRequest;

    public function add(TravelRequest $travelRequest): void;

    /**
     * Persists only if the stored version is still the one the aggregate was loaded with.
     *
     * @throws TravelRequestVersionMismatch
     */
    public function update(TravelRequest $travelRequest, int $loadedVersion): void;
}
