<?php

declare(strict_types=1);

namespace Tests\Support\TravelRequest\Fixture;

use Application\TravelRequest\Output\TravelRequestOutput;
use Application\TravelRequest\Output\UserReferenceOutput;
use DateTimeImmutable;
use DateTimeZone;
use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\ValueObject\Destination;
use Domain\TravelRequest\ValueObject\TravelPeriod;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Domain\TravelRequest\ValueObject\UserId;
use LogicException;

final class TravelRequestMother
{
    public const string TRAVEL_REQUEST_ID = '019a6a10-aaaa-7bbb-8ccc-000000000001';

    public const string REQUESTER_ID = '019a6a10-aaaa-7bbb-8ccc-0000000000a1';

    public const string ADMIN_ID = '019a6a10-aaaa-7bbb-8ccc-0000000000b1';

    public const string OTHER_USER_ID = '019a6a10-aaaa-7bbb-8ccc-0000000000c1';

    public const array USER_NAMES = [
        self::REQUESTER_ID => 'Monkey D. Luffy',
        self::ADMIN_ID => 'Nico Robin',
        self::OTHER_USER_ID => 'Roronoa Zoro',
    ];

    public const string DESTINATION = 'Lisbon';

    public const string NOW = '2026-11-09 10:00:00';

    public const string LATER = '2026-11-10 08:30:00';

    public const string DEPARTURE_DATE = '2026-12-01';

    public const string RETURN_DATE = '2026-12-05';

    public static function moment(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('UTC'));
    }

    public static function period(string $departureDate = self::DEPARTURE_DATE, string $returnDate = self::RETURN_DATE): TravelPeriod
    {
        return TravelPeriod::between(
            departureDate: self::moment(moment: $departureDate),
            returnDate: self::moment(moment: $returnDate),
        );
    }

    public static function userId(string $id): UserId
    {
        return UserId::fromString(id: $id);
    }

    public static function requested(string $requesterId = self::REQUESTER_ID): TravelRequest
    {
        return TravelRequest::request(
            id: TravelRequestId::fromString(id: self::TRAVEL_REQUEST_ID),
            requesterId: UserId::fromString(id: $requesterId),
            destination: Destination::fromString(name: self::DESTINATION),
            period: self::period(),
            requestedAt: self::moment(moment: self::NOW),
        );
    }

    /**
     * Any state at all, as if it had been read back from storage.
     */
    public static function stored(
        string $id = self::TRAVEL_REQUEST_ID,
        string $requesterId = self::REQUESTER_ID,
        string $destination = self::DESTINATION,
        string $departureDate = self::DEPARTURE_DATE,
        string $returnDate = self::RETURN_DATE,
        TravelRequestStatus $status = TravelRequestStatus::Requested,
        string $createdAt = self::NOW,
    ): TravelRequest {
        $isDecided = $status !== TravelRequestStatus::Requested;

        return TravelRequest::reconstitute(
            id: TravelRequestId::fromString(id: $id),
            requesterId: UserId::fromString(id: $requesterId),
            destination: Destination::fromString(name: $destination),
            period: self::period(departureDate: $departureDate, returnDate: $returnDate),
            status: $status,
            version: $isDecided ? TravelRequest::FIRST_VERSION + 1 : TravelRequest::FIRST_VERSION,
            createdAt: self::moment(moment: $createdAt),
            updatedAt: self::moment(moment: $createdAt),
            decidedBy: $isDecided ? UserId::fromString(id: self::ADMIN_ID) : null,
        );
    }

    /**
     * What the read side answers for the request, with the names of the fixture users.
     */
    public static function output(TravelRequest $travelRequest): TravelRequestOutput
    {
        $decidedBy = $travelRequest->decidedBy === null ? null : self::userReference(id: $travelRequest->decidedBy->toString());

        $requester = self::userReference(id: $travelRequest->requesterId->toString());

        return new TravelRequestOutput(
            id: $travelRequest->id->toString(),
            requester: $requester,
            destination: $travelRequest->destination->name,
            departureDate: $travelRequest->period->departureDate,
            returnDate: $travelRequest->period->returnDate,
            status: $travelRequest->status,
            version: $travelRequest->version,
            createdAt: $travelRequest->createdAt,
            updatedAt: $travelRequest->updatedAt,
            decidedBy: $decidedBy,
        );
    }

    public static function userReference(string $id): UserReferenceOutput
    {
        if (! \array_key_exists($id, self::USER_NAMES)) {
            throw new LogicException(\sprintf('User %s is not one of the fixture users.', $id));
        }

        return new UserReferenceOutput(id: $id, name: self::USER_NAMES[$id]);
    }

    public static function approved(): TravelRequest
    {
        $travelRequest = self::requested();
        $travelRequest->approve(
            approverId: self::userId(id: self::ADMIN_ID),
            approvedAt: self::moment(moment: self::LATER),
        );
        $travelRequest->releaseEvents();

        return $travelRequest;
    }

    public static function cancelled(): TravelRequest
    {
        $travelRequest = self::requested();
        $travelRequest->cancel(
            cancellerId: self::userId(id: self::ADMIN_ID),
            cancelledAt: self::moment(moment: self::LATER),
        );
        $travelRequest->releaseEvents();

        return $travelRequest;
    }
}
