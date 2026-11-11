<?php

declare(strict_types=1);

namespace Infrastructure\TravelRequest\Query;

use Application\TravelRequest\Input\TravelRequestFilters;
use Application\TravelRequest\Output\TravelRequestOutput;
use Application\TravelRequest\Output\TravelRequestPage;
use Application\TravelRequest\Port\TravelRequestListCriteria;
use Application\TravelRequest\Port\TravelRequestQueries;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Infrastructure\Shared\Persistence\BinaryUuid;
use Infrastructure\TravelRequest\Persistence\TravelRequestModel;
use Infrastructure\TravelRequest\Persistence\TravelRequestRows;

final readonly class DatabaseTravelRequestQueries implements TravelRequestQueries
{
    private const string LIKE_WILDCARDS = '\\%_';

    private const string NEXT_DAY = '+1 day';

    private const int ONE_EXTRA_ROW = 1;

    public function find(TravelRequestId $id): ?TravelRequestOutput
    {
        $idBytes = BinaryUuid::toBytes(uuid: $id->toString());
        $travelRequestRow = TravelRequestModel::query()
            ->with(TravelRequestModel::WITH_USERS)
            ->whereKey($idBytes)
            ->first();

        return $travelRequestRow === null ? null : TravelRequestRows::toOutput(travelRequestRow: $travelRequestRow);
    }

    public function get(TravelRequestId $id): TravelRequestOutput
    {
        $travelRequest = $this->find(id: $id);

        if ($travelRequest === null) {
            throw TravelRequestNotFound::withId(id: $id);
        }

        return $travelRequest;
    }

    /**
     * Keyset pagination on id, which is time-ordered: one extra row tells whether there is another page.
     */
    public function list(TravelRequestListCriteria $criteria): TravelRequestPage
    {
        $cursor = $criteria->cursor === null ? null : ListCursor::decode(encodedCursor: $criteria->cursor);
        $isGoingNewer = $cursor?->direction === ListDirection::Newer;
        $query = $this->filtered(criteria: $criteria);

        if ($cursor !== null) {
            $edgeIdBytes = BinaryUuid::toBytes(uuid: $cursor->edgeId->toString());
            $query->where('id', $isGoingNewer ? '>' : '<', $edgeIdBytes);
        }

        $rows = $query->orderBy('id', $isGoingNewer ? 'asc' : 'desc')
            ->limit($criteria->perPage + self::ONE_EXTRA_ROW)
            ->get();
        $hasMoreInThatDirection = $rows->count() > $criteria->perPage;
        $pageRows = $rows->take($criteria->perPage)
            ->all();
        $newestFirstRows = $isGoingNewer ? array_reverse($pageRows) : $pageRows;
        $travelRequestRows = TravelRequestModel::query()
            ->hydrate($newestFirstRows)
            ->load(TravelRequestModel::WITH_USERS);
        $travelRequests = [];

        foreach ($travelRequestRows as $travelRequestRow) {
            $travelRequests[] = TravelRequestRows::toOutput(travelRequestRow: $travelRequestRow);
        }

        return $this->page(
            travelRequests: $travelRequests,
            criteria: $criteria,
            hasOlder: $isGoingNewer || $hasMoreInThatDirection,
            hasNewer: $isGoingNewer ? $hasMoreInThatDirection : $cursor !== null,
        );
    }

    private function filtered(TravelRequestListCriteria $criteria): QueryBuilder
    {
        $filters = $criteria->filters;
        $scopedQuery = $criteria->requesterId === null
            ? TravelRequestModel::query()
            : TravelRequestModel::ownedBy($criteria->requesterId);
        $query = $scopedQuery->toBase();

        if ($filters->statuses !== []) {
            $statusValues = array_map(
                static fn(TravelRequestStatus $status): string => $status->value,
                $filters->statuses,
            );
            $query->whereIn('status', $statusValues);
        }

        if ($filters->destination !== null) {
            $escapedDestination = addcslashes($filters->destination, self::LIKE_WILDCARDS);
            $query->where('destination', 'like', '%' . $escapedDestination . '%');
        }

        $this->filterByDates(query: $query, filters: $filters);

        return $query;
    }

    /**
     * Creation days are inclusive; the trip filter keeps any trip that overlaps the given range.
     */
    private function filterByDates(QueryBuilder $query, TravelRequestFilters $filters): void
    {
        if ($filters->createdFrom !== null) {
            $query->where('created_at', '>=', $filters->createdFrom);
        }

        if ($filters->createdTo !== null) {
            $query->where('created_at', '<', $filters->createdTo->modify(self::NEXT_DAY));
        }

        if ($filters->travelFrom !== null) {
            $query->where('return_date', '>=', $filters->travelFrom->format(TravelRequestRows::CALENDAR_DATE));
        }

        if ($filters->travelTo !== null) {
            $query->where('departure_date', '<=', $filters->travelTo->format(TravelRequestRows::CALENDAR_DATE));
        }
    }

    /**
     * @param list<TravelRequestOutput> $travelRequests newest first
     */
    private function page(
        array $travelRequests,
        TravelRequestListCriteria $criteria,
        bool $hasOlder,
        bool $hasNewer,
    ): TravelRequestPage {
        $oldest = $travelRequests[array_key_last($travelRequests) ?? 0] ?? null;
        $newest = $travelRequests[0] ?? null;
        $nextCursor = $hasOlder && $oldest !== null ? ListCursor::olderThan(edgeId: $oldest->id) : null;
        $previousCursor = $hasNewer && $newest !== null ? ListCursor::newerThan(edgeId: $newest->id) : null;

        return new TravelRequestPage(
            travelRequests: $travelRequests,
            perPage: $criteria->perPage,
            nextCursor: $nextCursor?->encode(),
            previousCursor: $previousCursor?->encode(),
        );
    }
}
