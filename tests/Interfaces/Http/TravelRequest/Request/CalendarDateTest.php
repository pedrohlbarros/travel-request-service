<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\TravelRequest\Request;

use Illuminate\Http\Request;
use Interfaces\Http\TravelRequest\Request\CalendarDate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CalendarDateTest extends TestCase
{
    private const string FIELD = 'departure_date';

    public function test_ReadsTheDateAsMidnightUtc(): void
    {
        $request = new Request(query: [self::FIELD => '2026-11-20']);

        $date = CalendarDate::required(request: $request, field: self::FIELD);

        self::assertSame('2026-11-20T00:00:00+00:00', $date->format(DATE_ATOM));
    }

    public function test_AnAbsentOptionalDateIsNull(): void
    {
        $request = new Request();

        $date = CalendarDate::optional(request: $request, field: self::FIELD);

        self::assertNull($date);
    }

    public function test_ARequiredDateThatIsAbsentFailsLoudly(): void
    {
        $request = new Request();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('"departure_date" was validated as a required date.');

        CalendarDate::required(request: $request, field: self::FIELD);
    }
}
