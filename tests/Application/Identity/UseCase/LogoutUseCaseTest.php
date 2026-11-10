<?php

declare(strict_types=1);

namespace Tests\Application\Identity\UseCase;

use Application\Identity\Input\LoginInput;
use Application\Identity\Input\LogoutInput;
use Application\Identity\UseCase\LoginUseCase;
use Application\Identity\UseCase\LogoutUseCase;
use Domain\Identity\ValueObject\RefreshTokenHash;
use Tests\Support\Identity\Fixture\IdentityMother as Mother;

final class LogoutUseCaseTest extends IdentityUseCaseTestCase
{
    private const string ACCESS_TOKEN_ID = 'token-1';

    private const string ACCESS_TOKEN_EXPIRES_AT = '2026-11-09 10:15:00';

    private LogoutUseCase $useCase;

    private string $refreshToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new LogoutUseCase(
            revokedAccessTokens: $this->revokedAccessTokens,
            refreshTokens: $this->refreshTokens,
            clock: $this->clock,
        );
        $this->users->add(user: Mother::user());
        $this->users->add(user: Mother::admin());
        $login = new LoginUseCase(
            users: $this->users,
            passwordHasher: $this->passwordHasher,
            tokenPairs: $this->tokenPairs,
            identifiers: $this->identifiers,
            clock: $this->clock,
        );
        $this->refreshToken = $login->execute(input: new LoginInput(
            email: Mother::EMAIL,
            password: Mother::PASSWORD,
        ))->refreshToken;
    }

    public function test_RevokesTheAccessTokenUntilItExpires(): void
    {
        $this->useCase->execute(input: $this->logoutOf(userId: Mother::USER_ID, refreshToken: $this->refreshToken));

        self::assertTrue($this->revokedAccessTokens->isRevoked(tokenId: self::ACCESS_TOKEN_ID));
        self::assertEquals(
            Mother::moment(moment: self::ACCESS_TOKEN_EXPIRES_AT),
            $this->revokedAccessTokens->revokedUntilByTokenId[self::ACCESS_TOKEN_ID],
        );
    }

    public function test_RevokesTheRefreshTokenFamily(): void
    {
        $this->useCase->execute(input: $this->logoutOf(userId: Mother::USER_ID, refreshToken: $this->refreshToken));

        $storedToken = $this->refreshTokens->findByHash(tokenHash: RefreshTokenHash::of(refreshToken: $this->refreshToken));

        self::assertEquals(Mother::moment(), $storedToken?->revokedAt);
    }

    public function test_IgnoresARefreshTokenOfAnotherUser(): void
    {
        $this->useCase->execute(input: $this->logoutOf(userId: Mother::ADMIN_ID, refreshToken: $this->refreshToken));

        $storedToken = $this->refreshTokens->findByHash(tokenHash: RefreshTokenHash::of(refreshToken: $this->refreshToken));

        self::assertNull($storedToken?->revokedAt);
        self::assertTrue($this->revokedAccessTokens->isRevoked(tokenId: self::ACCESS_TOKEN_ID));
    }

    public function test_UnknownRefreshTokenStillEndsTheAccess(): void
    {
        $this->useCase->execute(input: $this->logoutOf(userId: Mother::USER_ID, refreshToken: 'never-issued'));

        self::assertTrue($this->revokedAccessTokens->isRevoked(tokenId: self::ACCESS_TOKEN_ID));
        self::assertNull($this->refreshTokens->all()[0]->revokedAt);
    }

    private function logoutOf(string $userId, string $refreshToken): LogoutInput
    {
        return new LogoutInput(
            userId: $userId,
            accessTokenId: self::ACCESS_TOKEN_ID,
            accessTokenExpiresAt: Mother::moment(moment: self::ACCESS_TOKEN_EXPIRES_AT),
            refreshToken: $refreshToken,
        );
    }
}
