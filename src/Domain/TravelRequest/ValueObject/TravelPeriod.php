<?php

declare(strict_types=1);

namespace Domain\TravelRequest\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use Domain\TravelRequest\Exception\InvalidTravelPeriod;

final readonly class TravelPeriod
{
    private const string CALENDAR_DATE = 'Y-m-d';

    private const string CALENDAR_TIMEZONE = 'UTC';

    private function __construct(
        public DateTimeImmutable $departureDate,
        public DateTimeImmutable $returnDate,
    ) {}

    /**
     * Only the calendar date of each argument is kept, so a time of day or timezone never shifts the trip.
     *
     * @throws InvalidTravelPeriod
     */
    public static function between(DateTimeImmutable $departureDate, DateTimeImmutable $returnDate): self
    {
        $departureDay = self::calendarDayOf($departureDate);
        $returnDay = self::calendarDayOf($returnDate);

        if ($returnDay < $departureDay) {
            throw InvalidTravelPeriod::returnBeforeDeparture(
                $departureDay->format(self::CALENDAR_DATE),
                $returnDay->format(self::CALENDAR_DATE),
            );
        }

        return new self($departureDay, $returnDay);
    }

    /**
     * @throws InvalidTravelPeriod
     */
    public function assertDepartsOnOrAfterDayOf(DateTimeImmutable $moment): void
    {
        $today = self::calendarDayOf($moment->setTimezone(new DateTimeZone(self::CALENDAR_TIMEZONE)));

        if ($this->departureDate < $today) {
            throw InvalidTravelPeriod::departureInThePast(
                $this->departureDate->format(self::CALENDAR_DATE),
                $today->format(self::CALENDAR_DATE),
            );
        }
    }

    private static function calendarDayOf(DateTimeImmutable $moment): DateTimeImmutable
    {
        return new DateTimeImmutable(
            $moment->format(self::CALENDAR_DATE),
            new DateTimeZone(self::CALENDAR_TIMEZONE),
        );
    }
}
