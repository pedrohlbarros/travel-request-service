<?php

declare(strict_types=1);

namespace Domain\Identity\Exception;

use Domain\Shared\Exception\BusinessRuleViolation;

final class InvalidUserName extends BusinessRuleViolation
{
    public static function lengthOutOfRange(int $shortestName, int $longestName): self
    {
        $message = \sprintf('The name must have between %d and %d characters.', $shortestName, $longestName);

        return new self($message);
    }
}
