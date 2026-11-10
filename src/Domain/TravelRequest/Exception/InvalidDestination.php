<?php

declare(strict_types=1);

namespace Domain\TravelRequest\Exception;

use Domain\Shared\Exception\BusinessRuleViolation;

final class InvalidDestination extends BusinessRuleViolation
{
    public static function lengthOutOfRange(string $name, int $shortestName, int $longestName): self
    {
        return new self(\sprintf(
            'Destination "%s" must have between %d and %d characters.',
            $name,
            $shortestName,
            $longestName,
        ));
    }
}
