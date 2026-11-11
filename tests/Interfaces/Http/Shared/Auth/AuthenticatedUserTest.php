<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Shared\Auth;

use DateTimeImmutable;
use Interfaces\Http\Shared\Auth\AuthenticatedUser;
use PHPUnit\Framework\TestCase;

final class AuthenticatedUserTest extends TestCase
{
    private const string USER_ID = '019a6c3e-7b1d-7c2a-9f4e-1a2b3c4d5e6f';

    private const string ACCESS_TOKEN_ID = '019a6c3e-7b1d-7c2a-9f4e-6f5e4d3c2b1a';

    private const string ACCESS_TOKEN_EXPIRES_AT = '2026-11-10 10:15:00';

    public function test_IsIdentifiedByItsIdAndHasNoPasswordOrRememberToken(): void
    {
        $user = $this->authenticatedUser();

        $user->setRememberToken(value: 'ignored');

        self::assertSame('id', $user->getAuthIdentifierName());
        self::assertSame(self::USER_ID, $user->getAuthIdentifier());
        self::assertSame('', $user->getAuthPasswordName());
        self::assertSame('', $user->getAuthPassword());
        self::assertSame('', $user->getRememberToken());
        self::assertSame('', $user->getRememberTokenName());
    }

    private function authenticatedUser(): AuthenticatedUser
    {
        return new AuthenticatedUser(
            id: self::USER_ID,
            isAdmin: false,
            accessTokenId: self::ACCESS_TOKEN_ID,
            accessTokenExpiresAt: new DateTimeImmutable(self::ACCESS_TOKEN_EXPIRES_AT),
        );
    }
}
