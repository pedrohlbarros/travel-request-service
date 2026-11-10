<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObject;

use SensitiveParameter;

/**
 * Only the hash of a refresh token is stored, so a leaked table does not hand out usable tokens.
 */
final readonly class RefreshTokenHash
{
    private const string ALGORITHM = 'sha256';

    private function __construct(
        public string $value,
    ) {}

    public static function of(#[SensitiveParameter] string $refreshToken): self
    {
        return new self(value: hash(self::ALGORITHM, $refreshToken));
    }

    public static function fromStored(string $storedHash): self
    {
        return new self(value: $storedHash);
    }
}
