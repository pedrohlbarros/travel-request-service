<?php

declare(strict_types=1);

namespace Domain\Identity\Exception;

use Domain\Shared\Exception\BusinessRuleViolation;

final class InvalidEmail extends BusinessRuleViolation
{
    public static function malformed(string $address): self
    {
        $message = \sprintf('"%s" is not a valid email address.', $address);

        return new self($message);
    }
}
