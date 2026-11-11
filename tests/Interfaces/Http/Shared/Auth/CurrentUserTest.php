<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Shared\Auth;

use DateTimeImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Interfaces\Http\Shared\Auth\AuthenticatedUser;
use Interfaces\Http\Shared\Auth\CurrentUser;
use PHPUnit\Framework\TestCase;

final class CurrentUserTest extends TestCase
{
    private const string USER_ID = '019a6c3e-7b1d-7c2a-9f4e-1a2b3c4d5e6f';

    private const string ACCESS_TOKEN_ID = '019a6c3e-7b1d-7c2a-9f4e-6f5e4d3c2b1a';

    private const string ACCESS_TOKEN_EXPIRES_AT = '2026-11-10 10:15:00';

    public function test_CurrentUserIsTheOneTheGuardResolved(): void
    {
        $user = $this->authenticatedUser();
        $request = new Request();
        $request->setUserResolver(static fn(): AuthenticatedUser => $user);

        $currentUser = CurrentUser::of(request: $request);

        self::assertSame($user, $currentUser);
    }

    public function test_ARequestWithoutAnAuthenticatedUserIsUnauthenticated(): void
    {
        $anonymousRequest = new Request();

        $this->expectException(AuthenticationException::class);

        CurrentUser::of(request: $anonymousRequest);
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
