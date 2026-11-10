<?php

declare(strict_types=1);

namespace Domain\TravelRequest\Exception;

use Domain\Shared\Exception\BusinessRuleViolation;
use Domain\TravelRequest\ValueObject\TravelRequestId;

final class TravelRequestVersionMismatch extends BusinessRuleViolation
{
    public static function expected(TravelRequestId $id, int $expectedVersion, int $currentVersion): self
    {
        return new self(\sprintf(
            'Travel request %s is at version %d, not %d.',
            $id->toString(),
            $currentVersion,
            $expectedVersion,
        ));
    }
}
