<?php

declare(strict_types=1);

namespace Domain\Identity\Exception;

use Domain\Shared\Exception\BusinessRuleViolation;

final class InvalidRefreshToken extends BusinessRuleViolation
{
    public static function unknown(): self
    {
        return new self('The refresh token is not valid.');
    }

    public static function expired(): self
    {
        return new self('The refresh token has expired.');
    }

    public static function revoked(): self
    {
        return new self('The refresh token was revoked.');
    }

    public static function reused(): self
    {
        return new self('The refresh token was already used; every session started from it was revoked.');
    }
}
