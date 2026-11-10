<?php

declare(strict_types=1);

namespace Domain\TravelRequest\Exception;

use Domain\Shared\Exception\BusinessRuleViolation;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Domain\TravelRequest\ValueObject\UserId;

final class TravelRequestNotOwned extends BusinessRuleViolation
{
    public static function by(TravelRequestId $id, UserId $userId): self
    {
        $message = \sprintf('Travel request %s does not belong to user %s.', $id->toString(), $userId->toString());

        return new self($message);
    }
}
