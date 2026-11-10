<?php

declare(strict_types=1);

namespace Domain\TravelRequest\Exception;

use Domain\Shared\Exception\BusinessRuleViolation;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\ValueObject\TravelRequestId;

final class TravelRequestNotEditable extends BusinessRuleViolation
{
    public static function inStatus(TravelRequestId $id, TravelRequestStatus $status): self
    {
        $message = \sprintf(
            'Travel request %s is %s and can no longer be edited.',
            $id->toString(),
            $status->value,
        );

        return new self($message);
    }
}
