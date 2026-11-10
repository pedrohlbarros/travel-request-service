<?php

declare(strict_types=1);

namespace Domain\TravelRequest\Exception;

use Domain\Shared\Exception\BusinessRuleViolation;
use Domain\TravelRequest\ValueObject\TravelRequestId;

final class TravelRequestNotFound extends BusinessRuleViolation
{
    public static function withId(TravelRequestId $id): self
    {
        return new self(\sprintf('Travel request %s was not found.', $id->toString()));
    }
}
