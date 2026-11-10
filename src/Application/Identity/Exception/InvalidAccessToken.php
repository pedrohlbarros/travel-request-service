<?php

declare(strict_types=1);

namespace Application\Identity\Exception;

use RuntimeException;

final class InvalidAccessToken extends RuntimeException
{
    public static function because(string $reason): self
    {
        $message = \sprintf('The access token is not valid: %s.', $reason);

        return new self($message);
    }
}
