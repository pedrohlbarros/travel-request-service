<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Security;

use Application\Identity\Port\PasswordHasher;
use Domain\Identity\ValueObject\PasswordHash;
use Domain\Identity\ValueObject\PlainPassword;
use Illuminate\Hashing\HashManager;
use SensitiveParameter;

final readonly class LaravelPasswordHasher implements PasswordHasher
{
    /** Hash of a random value nobody knows, checked when the email is unknown so the response takes as long. */
    private const string HASH_FOR_UNKNOWN_USERS = '$argon2id$v=19$m=65536,t=4,p=1$ME1DN09KTDRCVlIzN2pRQQ$jXSQyjeaX9TslxDpYM/myZ+9w8pzspdXhzOTwDBRc40';

    public function __construct(
        private HashManager $hashes,
    ) {}

    public function hash(PlainPassword $password): PasswordHash
    {
        return new PasswordHash(value: $this->hashes->createArgon2idDriver()
            ->make($password->value));
    }

    public function verify(#[SensitiveParameter] string $password, ?PasswordHash $hash): bool
    {
        $isMatch = $this->hashes->createArgon2idDriver()
            ->check($password, $hash->value ?? self::HASH_FOR_UNKNOWN_USERS);

        return $hash !== null && $isMatch;
    }
}
