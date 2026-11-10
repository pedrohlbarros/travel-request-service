<?php

declare(strict_types=1);

namespace Domain\TravelRequest\Exception;

use Domain\Shared\Exception\BusinessRuleViolation;

final class InvalidTravelPeriod extends BusinessRuleViolation
{
    public static function returnBeforeDeparture(string $departureDate, string $returnDate): self
    {
        $message = \sprintf('Return date %s is before departure date %s.', $returnDate, $departureDate);

        return new self($message);
    }

    public static function departureInThePast(string $departureDate, string $today): self
    {
        $message = \sprintf('Departure date %s is before today (%s).', $departureDate, $today);

        return new self($message);
    }
}
