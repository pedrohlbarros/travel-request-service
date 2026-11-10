<?php

declare(strict_types=1);

namespace Domain\Identity\Exception;

use Domain\Identity\ValueObject\Email;
use Domain\Shared\Exception\BusinessRuleViolation;

final class EmailAlreadyRegistered extends BusinessRuleViolation
{
    public static function address(Email $email): self
    {
        $message = \sprintf('The email %s is already registered.', $email->address);

        return new self($message);
    }
}
