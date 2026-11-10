<?php

declare(strict_types=1);

namespace Domain\Shared\Exception;

final class InvalidIdentifier extends BusinessRuleViolation
{
    public static function notUuid7(string $candidate): self
    {
        return new self(\sprintf('"%s" is not a canonical UUIDv7.', $candidate));
    }
}
