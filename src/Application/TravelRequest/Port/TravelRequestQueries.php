<?php

declare(strict_types=1);

namespace Application\TravelRequest\Port;

use Application\Shared\Exception\InvalidListCursor;
use Application\TravelRequest\Output\TravelRequestOutput;
use Application\TravelRequest\Output\TravelRequestPage;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\ValueObject\TravelRequestId;

/**
 * Read side of travel requests: answers straight from storage, without loading the aggregate.
 */
interface TravelRequestQueries
{
    public function find(TravelRequestId $id): ?TravelRequestOutput;

    /**
     * For a request that must exist, such as one just written.
     *
     * @throws TravelRequestNotFound
     */
    public function get(TravelRequestId $id): TravelRequestOutput;

    /**
     * Newest first.
     *
     * @throws InvalidListCursor
     */
    public function list(TravelRequestListCriteria $criteria): TravelRequestPage;
}
