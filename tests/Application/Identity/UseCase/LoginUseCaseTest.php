<?php

declare(strict_types=1);

namespace Tests\Application\Identity\UseCase;

use Application\Identity\Input\LoginInput;
use Application\Identity\UseCase\LoginUseCase;
use Domain\Identity\Exception\InvalidCredentials;
use Domain\Identity\ValueObject\RefreshTokenHash;
use Tests\Support\Identity\Fixture\IdentityMother as Mother;
use Tests\Support\Identity\Port\FakeAccessTokens;
use Tests\Support\Identity\Port\SequentialOpaqueTokenGenerator;
use Tests\Support\Shared\Port\SequentialIdentifierGenerator;

final class LoginUseCaseTest extends IdentityUseCaseTestCase
{
    private LoginUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new LoginUseCase(
            users: $this->users,
            passwordHasher: $this->passwordHasher,
            tokenPairs: $this->tokenPairs,
            identifiers: $this->identifiers,
            clock: $this->clock,
        );
        $this->users->add(user: Mother::user());
    }

    public function test_CorrectPasswordIssuesAccessAndRefreshTokens(): void
    {
        $tokens = $this->useCase->execute(input: new LoginInput(
            email: 'LUFFY@example.com',
            password: Mother::PASSWORD,
        ));

        $expectedAccessExpiry = Mother::moment(moment: '2026-11-09 10:15:00');

        self::assertSame(
            FakeAccessTokens::tokenFor(userId: Mother::USER_ID, tokenId: 'token-1', expiresAt: $expectedAccessExpiry),
            $tokens->accessToken,
        );
        self::assertSame(Mother::ACCESS_TOKEN_SECONDS, $tokens->accessTokenExpiresIn);
        self::assertSame(SequentialOpaqueTokenGenerator::nth(sequence: 1), $tokens->refreshToken);
        self::assertSame(Mother::REFRESH_TOKEN_SECONDS, $tokens->refreshTokenExpiresIn);
        self::assertEquals(Mother::moment(), $this->accessTokens->issuedTokens[0]['issuedAt']);
    }

    public function test_StoresOnlyTheHashOfTheRefreshTokenInANewFamily(): void
    {
        $tokens = $this->useCase->execute(input: new LoginInput(email: Mother::EMAIL, password: Mother::PASSWORD));

        [$storedToken] = $this->refreshTokens->all();

        self::assertSame(
            RefreshTokenHash::of(refreshToken: $tokens->refreshToken)->value,
            $storedToken->tokenHash->value,
        );
        self::assertSame(Mother::USER_ID, $storedToken->userId->toString());
        self::assertSame(SequentialIdentifierGenerator::nth(sequence: 1), $storedToken->familyId->toString());
        self::assertSame(SequentialIdentifierGenerator::nth(sequence: 2), $storedToken->id->toString());
        self::assertEquals(Mother::moment(), $storedToken->issuedAt);
        self::assertEquals(Mother::moment(moment: '2026-11-16 10:00:00'), $storedToken->expiresAt);
    }

    public function test_EachLoginStartsItsOwnFamily(): void
    {
        $this->useCase->execute(input: new LoginInput(email: Mother::EMAIL, password: Mother::PASSWORD));
        $this->useCase->execute(input: new LoginInput(email: Mother::EMAIL, password: Mother::PASSWORD));

        [$firstToken, $secondToken] = $this->refreshTokens->all();

        self::assertFalse($firstToken->familyId->equals(other: $secondToken->familyId));
    }

    public function test_RejectsWrongPassword(): void
    {
        $this->expectException(InvalidCredentials::class);

        $this->useCase->execute(input: new LoginInput(email: Mother::EMAIL, password: 'wrong-password'));
    }

    public function test_RejectsUnknownEmailAfterCheckingAPasswordAnyway(): void
    {
        try {
            $this->useCase->execute(input: new LoginInput(email: 'nobody@example.com', password: Mother::PASSWORD));
            self::fail('An unknown email must be rejected.');
        } catch (InvalidCredentials) {
        }

        self::assertSame(1, $this->passwordHasher->verifications);
        self::assertSame([], $this->refreshTokens->all());
    }
}
