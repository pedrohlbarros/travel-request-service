<?php

declare(strict_types=1);

namespace Tests\Domain\Identity\ValueObject;

use Domain\Identity\ValueObject\RefreshTokenHash;
use PHPUnit\Framework\TestCase;

final class RefreshTokenHashTest extends TestCase
{
    private const string REFRESH_TOKEN = 'opaque-refresh-token';

    public function test_StoresTheSha256OfTheToken(): void
    {
        $tokenHash = RefreshTokenHash::of(refreshToken: self::REFRESH_TOKEN);

        self::assertSame(hash('sha256', self::REFRESH_TOKEN), $tokenHash->value);
        self::assertStringNotContainsString(self::REFRESH_TOKEN, $tokenHash->value);
    }

    public function test_KeepsAStoredHashAsItIs(): void
    {
        $storedHash = hash('sha256', self::REFRESH_TOKEN);

        self::assertSame($storedHash, RefreshTokenHash::fromStored(storedHash: $storedHash)->value);
    }
}
