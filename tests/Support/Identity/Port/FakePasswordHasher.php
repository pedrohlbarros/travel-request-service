<?php

declare(strict_types=1);

namespace Tests\Support\Identity\Port;

use Application\Identity\Port\PasswordHasher;
use Domain\Identity\ValueObject\PasswordHash;
use Domain\Identity\ValueObject\PlainPassword;
use SensitiveParameter;

final class FakePasswordHasher implements PasswordHasher
{
    public const string HASH_PREFIX = 'hashed:';

    public private(set) int $hashings = 0;

    public private(set) int $verifications = 0;

    public function hash(PlainPassword $password): PasswordHash
    {
        $this->hashings++;

        return new PasswordHash(value: self::HASH_PREFIX . $password->value);
    }

    public function verify(#[SensitiveParameter] string $password, ?PasswordHash $hash): bool
    {
        $this->verifications++;

        return $hash !== null && $hash->value === self::HASH_PREFIX . $password;
    }
}
