<?php

declare(strict_types=1);

namespace Tests\Application\Identity\UseCase;

use Application\Identity\Exception\InvalidAccessToken;
use Application\Identity\UseCase\AuthenticateAccessTokenUseCase;
use Tests\Support\Identity\Fixture\IdentityMother as Mother;
use Tests\Support\Identity\Port\FakeAccessTokens;

final class AuthenticateAccessTokenUseCaseTest extends IdentityUseCaseTestCase
{
    private const string TOKEN_ID = 'token-1';

    private const string EXPIRES_AT = '2026-11-09 10:15:00';

    private AuthenticateAccessTokenUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new AuthenticateAccessTokenUseCase(
            verifier: $this->accessTokens,
            revokedAccessTokens: $this->revokedAccessTokens,
            roles: $this->users,
            clock: $this->clock,
        );
    }

    public function test_AuthenticatesAValidTokenOfAStoredUser(): void
    {
        $this->users->add(user: Mother::user());

        $authentication = $this->useCase->execute(accessToken: $this->tokenOf(userId: Mother::USER_ID));

        self::assertSame(Mother::USER_ID, $authentication->userId);
        self::assertFalse($authentication->isAdmin);
        self::assertSame(self::TOKEN_ID, $authentication->accessTokenId);
        self::assertEquals(Mother::moment(moment: self::EXPIRES_AT), $authentication->accessTokenExpiresAt);
    }

    public function test_ReadsTheRoleFromTheStoredUser(): void
    {
        $this->users->add(user: Mother::admin());

        $authentication = $this->useCase->execute(accessToken: $this->tokenOf(userId: Mother::ADMIN_ID));

        self::assertTrue($authentication->isAdmin);
    }

    public function test_RejectsARevokedToken(): void
    {
        $this->users->add(user: Mother::user());
        $this->revokedAccessTokens->revoke(tokenId: self::TOKEN_ID, until: Mother::moment(moment: self::EXPIRES_AT));

        $this->expectException(InvalidAccessToken::class);
        $this->expectExceptionMessageIs('The access token is not valid: it was revoked.');

        $this->useCase->execute(accessToken: $this->tokenOf(userId: Mother::USER_ID));
    }

    public function test_RejectsATokenOfAnUnknownUser(): void
    {
        $this->expectException(InvalidAccessToken::class);
        $this->expectExceptionMessageIs('The access token is not valid: its subject is not a user.');

        $this->useCase->execute(accessToken: $this->tokenOf(userId: Mother::USER_ID));
    }

    public function test_RejectsATokenWhoseSubjectIsNotAUserId(): void
    {
        $this->expectException(InvalidAccessToken::class);

        $this->useCase->execute(accessToken: $this->tokenOf(userId: 'not-a-uuid'));
    }

    public function test_RejectsATokenTheVerifierRefuses(): void
    {
        $this->users->add(user: Mother::user());
        $this->clock->travelTo(moment: Mother::moment(moment: self::EXPIRES_AT));

        $this->expectException(InvalidAccessToken::class);

        $this->useCase->execute(accessToken: $this->tokenOf(userId: Mother::USER_ID));
    }

    private function tokenOf(string $userId): string
    {
        return FakeAccessTokens::tokenFor(
            userId: $userId,
            tokenId: self::TOKEN_ID,
            expiresAt: Mother::moment(moment: self::EXPIRES_AT),
        );
    }
}
