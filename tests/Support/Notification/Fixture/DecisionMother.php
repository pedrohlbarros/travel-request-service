<?php

declare(strict_types=1);

namespace Tests\Support\Notification\Fixture;

use Application\Notification\Input\NotifyRequesterOfDecisionInput;
use DateTimeImmutable;
use DateTimeZone;
use Domain\Notification\ValueObject\Recipient;
use Domain\Notification\ValueObject\TravelRequestDecision;

final class DecisionMother
{
    public const string EVENT_ID = '019a6b00-0000-7000-8000-0000000000e1';

    public const string OTHER_EVENT_ID = '019a6b00-0000-7000-8000-0000000000e2';

    public const string TRAVEL_REQUEST_ID = '019a6b00-0000-7000-8000-0000000000a1';

    public const string REQUESTER_ID = '019a6b00-0000-7000-8000-0000000000b1';

    public const string OTHER_RECIPIENT_ID = '019a6b00-0000-7000-8000-0000000000b2';

    public const string REQUESTER_NAME = 'Monkey D. Luffy';

    public const string REQUESTER_EMAIL = 'luffy@example.com';

    public const string ADMIN_ID = '019a6b00-0000-7000-8000-0000000000c1';

    public const string DESTINATION = 'Wano';

    public const string DEPARTURE_DATE = '2026-12-01';

    public const string RETURN_DATE = '2026-12-05';

    public const string DECIDED_AT = '2026-11-10T09:30:00+00:00';

    public static function input(
        string $outcome = 'approved',
        string $eventId = self::EVENT_ID,
        string $requesterEmail = self::REQUESTER_EMAIL,
    ): NotifyRequesterOfDecisionInput {
        return new NotifyRequesterOfDecisionInput(
            eventId: $eventId,
            travelRequestId: self::TRAVEL_REQUEST_ID,
            requesterId: self::REQUESTER_ID,
            requesterName: self::REQUESTER_NAME,
            requesterEmail: $requesterEmail,
            destination: self::DESTINATION,
            departureDate: self::date(date: self::DEPARTURE_DATE),
            returnDate: self::date(date: self::RETURN_DATE),
            outcome: $outcome,
            decidedBy: self::ADMIN_ID,
            decidedAt: new DateTimeImmutable(self::DECIDED_AT),
        );
    }

    public static function decision(string $outcome = 'approved', string $eventId = self::EVENT_ID): TravelRequestDecision
    {
        return TravelRequestDecision::of(
            eventId: $eventId,
            travelRequestId: self::TRAVEL_REQUEST_ID,
            recipient: self::recipient(),
            destination: self::DESTINATION,
            departureDate: self::date(date: self::DEPARTURE_DATE),
            returnDate: self::date(date: self::RETURN_DATE),
            outcome: $outcome,
            decidedBy: self::ADMIN_ID,
            decidedAt: new DateTimeImmutable(self::DECIDED_AT),
        );
    }

    public static function recipient(): Recipient
    {
        return Recipient::of(id: self::REQUESTER_ID, name: self::REQUESTER_NAME, email: self::REQUESTER_EMAIL);
    }

    public static function date(string $date): DateTimeImmutable
    {
        $calendarDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        \assert($calendarDate instanceof DateTimeImmutable);

        return $calendarDate;
    }
}
