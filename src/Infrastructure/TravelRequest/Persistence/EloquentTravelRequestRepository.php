<?php

declare(strict_types=1);

namespace Infrastructure\TravelRequest\Persistence;

use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\Exception\TravelRequestVersionMismatch;
use Domain\TravelRequest\Repository\TravelRequestRepository;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Infrastructure\Shared\Persistence\BinaryUuid;

/**
 * Keeps the rows it handed out, so an update after a get writes the changed columns without reading the row
 * again. A new instance is made for every use case, so nothing outlives a request.
 *
 * Reads go to the primary: a write decides on what it reads, and the replica may not have the latest row yet.
 */
final class EloquentTravelRequestRepository implements TravelRequestRepository
{
    private const int ROWS_CHANGED_BY_A_WON_RACE = 1;

    /** @var array<string, TravelRequestModel> */
    private array $loadedRows = [];

    public function get(TravelRequestId $id): TravelRequest
    {
        $travelRequestRow = $this->findRow(id: $id);

        if ($travelRequestRow === null) {
            throw TravelRequestNotFound::withId(id: $id);
        }

        $this->loadedRows[$id->toString()] = $travelRequestRow;

        return TravelRequestRows::toEntity(travelRequestRow: $travelRequestRow);
    }

    public function add(TravelRequest $travelRequest): void
    {
        TravelRequestModel::query()
            ->create(TravelRequestRows::columnsOf(travelRequest: $travelRequest));
    }

    /**
     * Writes only the columns that changed, and only while the row is still at the loaded version.
     */
    public function update(TravelRequest $travelRequest, int $loadedVersion): void
    {
        $storedRow = $this->loadedRows[$travelRequest->id->toString()] ?? $this->findRow(id: $travelRequest->id);
        unset($this->loadedRows[$travelRequest->id->toString()]);

        if ($storedRow === null) {
            throw TravelRequestNotFound::withId(id: $travelRequest->id);
        }

        if ($storedRow->version !== $loadedVersion) {
            throw TravelRequestVersionMismatch::expected(
                id: $travelRequest->id,
                expectedVersion: $loadedVersion,
                currentVersion: $storedRow->version,
            );
        }

        $storedRow->fill(TravelRequestRows::columnsOf(travelRequest: $travelRequest));
        $updatedRows = TravelRequestModel::onPrimary()
            ->whereKey($storedRow->getRawOriginal('id'))
            ->where('version', $loadedVersion)
            ->update($storedRow->getDirty());

        if ($updatedRows !== self::ROWS_CHANGED_BY_A_WON_RACE) {
            $this->failLostRace(travelRequest: $travelRequest, loadedVersion: $loadedVersion);
        }
    }

    private function findRow(TravelRequestId $id): ?TravelRequestModel
    {
        $idBytes = BinaryUuid::toBytes(uuid: $id->toString());

        return TravelRequestModel::onPrimary()
            ->whereKey($idBytes)
            ->first();
    }

    /**
     * @throws TravelRequestVersionMismatch
     */
    private function failLostRace(TravelRequest $travelRequest, int $loadedVersion): never
    {
        $idBytes = BinaryUuid::toBytes(uuid: $travelRequest->id->toString());
        $currentVersion = TravelRequestModel::onPrimary()
            ->whereKey($idBytes)
            ->value('version');

        throw TravelRequestVersionMismatch::expected(
            id: $travelRequest->id,
            expectedVersion: $loadedVersion,
            currentVersion: is_numeric($currentVersion) ? (int) $currentVersion : $loadedVersion,
        );
    }
}
