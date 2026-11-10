<?php

declare(strict_types=1);

namespace Domain\Identity\Exception;

use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Exception\BusinessRuleViolation;

final class UserNotFound extends BusinessRuleViolation
{
    public static function withId(UserId $id): self
    {
        $message = \sprintf('User %s was not found.', $id->toString());

        return new self($message);
    }
}
