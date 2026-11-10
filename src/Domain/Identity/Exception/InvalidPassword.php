<?php

declare(strict_types=1);

namespace Domain\Identity\Exception;

use Domain\Shared\Exception\BusinessRuleViolation;

final class InvalidPassword extends BusinessRuleViolation
{
    public static function lengthOutOfRange(int $shortestPassword, int $longestPassword): self
    {
        $message = \sprintf(
            'The password must have between %d and %d characters.',
            $shortestPassword,
            $longestPassword,
        );

        return new self($message);
    }
}
