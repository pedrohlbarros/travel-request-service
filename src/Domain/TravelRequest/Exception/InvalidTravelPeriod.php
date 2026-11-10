<?php

declare(strict_types=1);

namespace Domain\TravelRequest\Exception;

use Domain\Shared\Exception\BusinessRuleViolation;

final class InvalidTravelPeriod extends BusinessRuleViolation
{
    public static function returnBeforeDeparture(string $departureDate, string $returnDate): self
    {
        return new self(\sprintf('Return date %s is before departure date %s.', $returnDate, $departureDate));
    }

    public static function departureInThePast(string $departureDate, string $today): self
    {
        return new self(\sprintf('Departure date %s is before today (%s).', $departureDate, $today));
    }
}
