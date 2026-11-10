<?php

declare(strict_types=1);

namespace Tests\Domain\TravelRequest\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use Domain\TravelRequest\Exception\InvalidTravelPeriod;
use Domain\TravelRequest\ValueObject\TravelPeriod;
use PHPUnit\Framework\TestCase;
use Tests\Support\TravelRequest\Fixture\TravelRequestMother;

final class TravelPeriodTest extends TestCase
{
    private const string CALENDAR_DATE = 'Y-m-d';

    public function test_KeepsDepartureAndReturnDates(): void
    {
        $period = TravelRequestMother::period('2026-12-01', '2026-12-05');

        self::assertSame('2026-12-01', $period->departureDate->format(self::CALENDAR_DATE));
        self::assertSame('2026-12-05', $period->returnDate->format(self::CALENDAR_DATE));
    }

    public function test_AllowsReturnOnDepartureDay(): void
    {
        $period = TravelRequestMother::period('2026-12-01', '2026-12-01');

        self::assertEquals($period->departureDate, $period->returnDate);
    }

    public function test_RejectsReturnBeforeDeparture(): void
    {
        $this->expectException(InvalidTravelPeriod::class);
        $this->expectExceptionMessageIs('Return date 2026-11-30 is before departure date 2026-12-01.');

        TravelRequestMother::period('2026-12-01', '2026-11-30');
    }

    public function test_KeepsOnlyTheCalendarDateAtMidnightUtc(): void
    {
        $departureLateAtNightInSaoPaulo = new DateTimeImmutable('2026-12-01 23:30:00', new DateTimeZone('America/Sao_Paulo'));
        $returnInTheMorningInTokyo = new DateTimeImmutable('2026-12-05 07:15:00', new DateTimeZone('Asia/Tokyo'));

        $period = TravelPeriod::between($departureLateAtNightInSaoPaulo, $returnInTheMorningInTokyo);

        self::assertSame('2026-12-01T00:00:00+00:00', $period->departureDate->format(DATE_ATOM));
        self::assertSame('2026-12-05T00:00:00+00:00', $period->returnDate->format(DATE_ATOM));
    }

    public function test_SameCalendarDayIsNotReturnBeforeDepartureEvenWithEarlierTime(): void
    {
        $departureInTheEvening = TravelRequestMother::moment('2026-12-01 20:00:00');
        $returnInTheMorning = TravelRequestMother::moment('2026-12-01 06:00:00');

        $period = TravelPeriod::between($departureInTheEvening, $returnInTheMorning);

        self::assertEquals($period->departureDate, $period->returnDate);
    }

    public function test_AcceptsDepartureToday(): void
    {
        $period = TravelRequestMother::period('2026-11-09', '2026-11-10');

        $period->assertDepartsOnOrAfterDayOf(TravelRequestMother::moment('2026-11-09 23:59:59'));

        $this->addToAssertionCount(1);
    }

    public function test_RejectsDepartureBeforeToday(): void
    {
        $period = TravelRequestMother::period('2026-11-08', '2026-11-10');

        $this->expectException(InvalidTravelPeriod::class);
        $this->expectExceptionMessageIs('Departure date 2026-11-08 is before today (2026-11-09).');

        $period->assertDepartsOnOrAfterDayOf(TravelRequestMother::moment('2026-11-09 00:00:00'));
    }

    public function test_TodayIsTheUtcCalendarDay(): void
    {
        $period = TravelRequestMother::period('2026-11-09', '2026-11-10');
        $alreadyTomorrowInUtc = new DateTimeImmutable('2026-11-09 22:00:00', new DateTimeZone('America/Sao_Paulo'));

        $this->expectException(InvalidTravelPeriod::class);
        $this->expectExceptionMessageIsOrContains('before today (2026-11-10)');

        $period->assertDepartsOnOrAfterDayOf($alreadyTomorrowInUtc);
    }
}
