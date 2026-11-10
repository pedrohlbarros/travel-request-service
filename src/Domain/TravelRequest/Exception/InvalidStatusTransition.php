<?php

declare(strict_types=1);

namespace Domain\TravelRequest\Exception;

use Domain\Shared\Exception\BusinessRuleViolation;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\ValueObject\TravelRequestId;

final class InvalidStatusTransition extends BusinessRuleViolation
{
    public static function between(
        TravelRequestId $id,
        TravelRequestStatus $currentStatus,
        TravelRequestStatus $targetStatus,
    ): self {
        $message = \sprintf(
            'Travel request %s cannot go from %s to %s.',
            $id->toString(),
            $currentStatus->value,
            $targetStatus->value,
        );

        return new self($message);
    }
}
