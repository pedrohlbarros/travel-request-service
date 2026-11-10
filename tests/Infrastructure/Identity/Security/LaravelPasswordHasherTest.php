<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Identity\Security;

use Domain\Identity\ValueObject\PasswordHash;
use Domain\Identity\ValueObject\PlainPassword;
use Infrastructure\Identity\Security\LaravelPasswordHasher;
use RuntimeException;
use Tests\Support\LaravelTestCase;

final class LaravelPasswordHasherTest extends LaravelTestCase
{
    private const string PASSWORD = 'gomu-gomu-no';

    private const string ARGON2ID_PREFIX = '$argon2id$';

    public function test_HashesWithArgon2id(): void
    {
        $hash = $this->hasher()
            ->hash(password: PlainPassword::fromString(password: self::PASSWORD));

        self::assertStringStartsWith(self::ARGON2ID_PREFIX, $hash->value);
        self::assertStringNotContainsString(self::PASSWORD, $hash->value);
    }

    public function test_VerifiesOnlyTheRightPassword(): void
    {
        $hasher = $this->hasher();
        $hash = $hasher->hash(password: PlainPassword::fromString(password: self::PASSWORD));

        self::assertTrue($hasher->verify(password: self::PASSWORD, hash: $hash));
        self::assertFalse($hasher->verify(password: 'wrong-password', hash: $hash));
    }

    public function test_WithoutAHashNothingVerifies(): void
    {
        self::assertFalse($this->hasher()
            ->verify(password: self::PASSWORD, hash: null));
    }

    public function test_RejectsAPasswordAgainstAnotherAlgorithmsHash(): void
    {
        $bcryptHash = new PasswordHash(value: password_hash(self::PASSWORD, PASSWORD_BCRYPT));

        $this->expectException(RuntimeException::class);

        $this->hasher()
            ->verify(password: self::PASSWORD, hash: $bcryptHash);
    }

    private function hasher(): LaravelPasswordHasher
    {
        return $this->app->make(LaravelPasswordHasher::class);
    }
}
