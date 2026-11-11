<?php

declare(strict_types=1);

namespace Infrastructure\TravelRequest\Persistence;

use Application\TravelRequest\Output\TravelRequestOutput;
use Application\TravelRequest\Output\UserReferenceOutput;
use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\ValueObject\Destination;
use Domain\TravelRequest\ValueObject\TravelPeriod;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Domain\TravelRequest\ValueObject\UserId;
use Infrastructure\Shared\Persistence\BinaryUuid;

/**
 * Maps a travel request to its row and back, for the repository and the read side alike.
 */
final readonly class TravelRequestRows
{
    public const string CALENDAR_DATE = 'Y-m-d';

    /**
     * @return array<string, mixed>
     */
    public static function columnsOf(TravelRequest $travelRequest): array
    {
        $decidedBy = $travelRequest->decidedBy === null ? null : BinaryUuid::toBytes(uuid: $travelRequest->decidedBy->toString());

        return [
            'id' => $travelRequest->id->toString(),
            'requester_id' => BinaryUuid::toBytes(uuid: $travelRequest->requesterId->toString()),
            'destination' => $travelRequest->destination->name,
            'departure_date' => $travelRequest->period->departureDate->format(self::CALENDAR_DATE),
            'return_date' => $travelRequest->period->returnDate->format(self::CALENDAR_DATE),
            'status' => $travelRequest->status->value,
            'version' => $travelRequest->version,
            'decided_by' => $decidedBy,
            'created_at' => $travelRequest->createdAt,
            'updated_at' => $travelRequest->updatedAt,
        ];
    }

    public static function toEntity(TravelRequestModel $travelRequestRow): TravelRequest
    {
        $decidedBy = $travelRequestRow->decided_by === null ? null : self::userIdFrom(bytes: $travelRequestRow->decided_by);

        return TravelRequest::reconstitute(
            id: TravelRequestId::fromString(id: $travelRequestRow->id),
            requesterId: self::userIdFrom(bytes: $travelRequestRow->requester_id),
            destination: Destination::fromString(name: $travelRequestRow->destination),
            period: self::periodOf(travelRequestRow: $travelRequestRow),
            status: TravelRequestStatus::from(value: $travelRequestRow->status),
            version: $travelRequestRow->version,
            createdAt: $travelRequestRow->created_at->toDateTimeImmutable(),
            updatedAt: $travelRequestRow->updated_at->toDateTimeImmutable(),
            decidedBy: $decidedBy,
        );
    }

    /**
     * Expects the users loaded through TravelRequestModel::WITH_USERS.
     */
    public static function toOutput(TravelRequestModel $travelRequestRow): TravelRequestOutput
    {
        $period = self::periodOf(travelRequestRow: $travelRequestRow);
        $decider = $travelRequestRow->decider;

        return new TravelRequestOutput(
            id: $travelRequestRow->id,
            requester: self::userReferenceOf(user: $travelRequestRow->requester),
            destination: $travelRequestRow->destination,
            departureDate: $period->departureDate,
            returnDate: $period->returnDate,
            status: TravelRequestStatus::from(value: $travelRequestRow->status),
            version: $travelRequestRow->version,
            createdAt: $travelRequestRow->created_at->toDateTimeImmutable(),
            updatedAt: $travelRequestRow->updated_at->toDateTimeImmutable(),
            decidedBy: $decider === null ? null : self::userReferenceOf(user: $decider),
        );
    }

    private static function periodOf(TravelRequestModel $travelRequestRow): TravelPeriod
    {
        return TravelPeriod::between(
            departureDate: $travelRequestRow->departure_date->toDateTimeImmutable(),
            returnDate: $travelRequestRow->return_date->toDateTimeImmutable(),
        );
    }

    private static function userIdFrom(string $bytes): UserId
    {
        return UserId::fromString(id: BinaryUuid::fromBytes(bytes: $bytes));
    }

    private static function userReferenceOf(RequesterModel $user): UserReferenceOutput
    {
        return new UserReferenceOutput(id: BinaryUuid::fromBytes(bytes: $user->id), name: $user->name);
    }
}
