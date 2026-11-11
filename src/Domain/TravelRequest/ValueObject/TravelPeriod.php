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
        $departureDay = self::calendarDayOf(moment: $departureDate);
        $returnDay = self::calendarDayOf(moment: $returnDate);

        if ($returnDay < $departureDay) {
            throw InvalidTravelPeriod::returnBeforeDeparture(
                departureDate: $departureDay->format(self::CALENDAR_DATE),
                returnDate: $returnDay->format(self::CALENDAR_DATE),
            );
        }

        return new self(departureDate: $departureDay, returnDate: $returnDay);
    }

    /**
     * @throws InvalidTravelPeriod
     */
    public function assertDepartsOnOrAfterDayOf(DateTimeImmutable $moment): void
    {
        $momentInCalendarTimezone = $moment->setTimezone(new DateTimeZone(self::CALENDAR_TIMEZONE));
        $today = self::calendarDayOf(moment: $momentInCalendarTimezone);

        if ($this->departureDate < $today) {
            throw InvalidTravelPeriod::departureInThePast(
                departureDate: $this->departureDate->format(self::CALENDAR_DATE),
                today: $today->format(self::CALENDAR_DATE),
            );
        }
    }

    public function equals(self $other): bool
    {
        $isSameDeparture = $this->departureDate->format(self::CALENDAR_DATE) === $other->departureDate->format(self::CALENDAR_DATE);
        $isSameReturn = $this->returnDate->format(self::CALENDAR_DATE) === $other->returnDate->format(self::CALENDAR_DATE);

        return $isSameDeparture && $isSameReturn;
    }

    private static function calendarDayOf(DateTimeImmutable $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment->format(self::CALENDAR_DATE), new DateTimeZone(self::CALENDAR_TIMEZONE));
    }
}
