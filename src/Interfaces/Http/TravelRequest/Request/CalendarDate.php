<?php

declare(strict_types=1);

namespace Interfaces\Http\TravelRequest\Request;

use DateTimeImmutable;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Calendar dates arrive as YYYY-MM-DD and are read as midnight UTC.
 */
final readonly class CalendarDate
{
    public const string FORMAT = 'Y-m-d';

    private const string TIMEZONE = 'UTC';

    public static function optional(Request $request, string $field): ?DateTimeImmutable
    {
        return $request->date($field, self::FORMAT, self::TIMEZONE)
            ?->startOfDay()
            ->toDateTimeImmutable();
    }

    public static function required(Request $request, string $field): DateTimeImmutable
    {
        $date = self::optional(request: $request, field: $field);

        if ($date === null) {
            throw new InvalidArgumentException(\sprintf('"%s" was validated as a required date.', $field));
        }

        return $date;
    }
}
