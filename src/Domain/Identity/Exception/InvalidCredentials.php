<?php

declare(strict_types=1);

namespace Domain\Identity\Exception;

use Domain\Shared\Exception\BusinessRuleViolation;

final class InvalidCredentials extends BusinessRuleViolation
{
    public static function create(): self
    {
        return new self('The email or password is incorrect.');
    }
}
