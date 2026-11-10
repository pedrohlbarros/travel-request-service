<?php

declare(strict_types=1);

namespace Tests\Support\TravelRequest\Fixture;

use DateTimeImmutable;
use DateTimeZone;
use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\ValueObject\Destination;
use Domain\TravelRequest\ValueObject\TravelPeriod;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Domain\TravelRequest\ValueObject\UserId;

final class TravelRequestMother
{
    public const string TRAVEL_REQUEST_ID = '019a6a10-aaaa-7bbb-8ccc-000000000001';

    public const string REQUESTER_ID = '019a6a10-aaaa-7bbb-8ccc-0000000000a1';

    public const string ADMIN_ID = '019a6a10-aaaa-7bbb-8ccc-0000000000b1';

    public const string OTHER_USER_ID = '019a6a10-aaaa-7bbb-8ccc-0000000000c1';

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
        return TravelPeriod::between(self::moment($departureDate), self::moment($returnDate));
    }

    public static function userId(string $id): UserId
    {
        return UserId::fromString($id);
    }

    public static function requested(string $requesterId = self::REQUESTER_ID): TravelRequest
    {
        return TravelRequest::request(
            TravelRequestId::fromString(self::TRAVEL_REQUEST_ID),
            UserId::fromString($requesterId),
            Destination::fromString(self::DESTINATION),
            self::period(),
            self::moment(self::NOW),
        );
    }

    public static function approved(): TravelRequest
    {
        $travelRequest = self::requested();
        $travelRequest->approve(self::userId(self::ADMIN_ID), self::moment(self::LATER));
        $travelRequest->releaseEvents();

        return $travelRequest;
    }

    public static function cancelled(): TravelRequest
    {
        $travelRequest = self::requested();
        $travelRequest->cancel(self::userId(self::ADMIN_ID), self::moment(self::LATER));
        $travelRequest->releaseEvents();

        return $travelRequest;
    }
}
