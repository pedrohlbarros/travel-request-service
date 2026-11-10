<?php

declare(strict_types=1);

namespace Tests\Application\Identity\UseCase;

use Application\Identity\Input\LoginInput;
use Application\Identity\UseCase\LoginUseCase;
use Application\Identity\UseCase\RefreshAccessTokenUseCase;
use Domain\Identity\Exception\InvalidRefreshToken;
use Domain\Identity\ValueObject\RefreshTokenHash;
use Tests\Support\Identity\Fixture\IdentityMother as Mother;
use Tests\Support\Identity\Port\SequentialOpaqueTokenGenerator;

final class RefreshAccessTokenUseCaseTest extends IdentityUseCaseTestCase
{
    private RefreshAccessTokenUseCase $useCase;

    private string $loginRefreshToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new RefreshAccessTokenUseCase(
            refreshTokens: $this->refreshTokens,
            tokenPairs: $this->tokenPairs,
            clock: $this->clock,
        );
        $this->users->add(user: Mother::user());
        $login = new LoginUseCase(
            users: $this->users,
            passwordHasher: $this->passwordHasher,
            tokenPairs: $this->tokenPairs,
            identifiers: $this->identifiers,
            clock: $this->clock,
        );
        $this->loginRefreshToken = $login->execute(input: new LoginInput(
            email: Mother::EMAIL,
            password: Mother::PASSWORD,
        ))->refreshToken;
    }

    public function test_RotatesTheRefreshTokenWithinTheSameFamily(): void
    {
        $this->clock->travelTo(moment: Mother::moment(moment: '2026-11-12 08:00:00'));

        $tokens = $this->useCase->execute(refreshToken: $this->loginRefreshToken);

        $loginToken = $this->refreshTokens->findByHash(tokenHash: RefreshTokenHash::of(refreshToken: $this->loginRefreshToken));
        $newToken = $this->refreshTokens->findByHash(tokenHash: RefreshTokenHash::of(refreshToken: $tokens->refreshToken));

        self::assertNotNull($loginToken);
        self::assertNotNull($newToken);
        self::assertSame(SequentialOpaqueTokenGenerator::nth(sequence: 2), $tokens->refreshToken);
        self::assertEquals(Mother::moment(moment: '2026-11-12 08:00:00'), $loginToken->rotatedAt);
        self::assertTrue($newToken->familyId->equals(other: $loginToken->familyId));
        self::assertFalse($newToken->wasRotated());
        self::assertEquals(Mother::moment(moment: '2026-11-19 08:00:00'), $newToken->expiresAt);
        self::assertCount(2, $this->accessTokens->issuedTokens);
    }

    public function test_ReusingARotatedTokenRevokesTheWholeFamily(): void
    {
        $rotatedOnce = $this->useCase->execute(refreshToken: $this->loginRefreshToken)->refreshToken;

        try {
            $this->useCase->execute(refreshToken: $this->loginRefreshToken);
            self::fail('A reused refresh token must be rejected.');
        } catch (InvalidRefreshToken $rejection) {
            self::assertSame(
                'The refresh token was already used; every session started from it was revoked.',
                $rejection->getMessage(),
            );
        }

        $this->expectException(InvalidRefreshToken::class);
        $this->expectExceptionMessageIs('The refresh token was revoked.');

        $this->useCase->execute(refreshToken: $rotatedOnce);
    }

    public function test_LosingAConcurrentRotationCountsAsReuse(): void
    {
        $this->refreshTokens->loseNextRotationToAnotherRequest();

        try {
            $this->useCase->execute(refreshToken: $this->loginRefreshToken);
            self::fail('A token rotated by a concurrent request must be rejected.');
        } catch (InvalidRefreshToken) {
        }

        $loginToken = $this->refreshTokens->findByHash(tokenHash: RefreshTokenHash::of(refreshToken: $this->loginRefreshToken));

        self::assertNotNull($loginToken?->revokedAt);
        self::assertCount(1, $this->refreshTokens->all());
    }

    public function test_RejectsUnknownToken(): void
    {
        $this->expectException(InvalidRefreshToken::class);
        $this->expectExceptionMessageIs('The refresh token is not valid.');

        $this->useCase->execute(refreshToken: 'never-issued');
    }

    public function test_RejectsExpiredTokenWithoutRevokingTheFamily(): void
    {
        $this->clock->travelTo(moment: Mother::moment(moment: '2026-11-16 10:00:00'));

        try {
            $this->useCase->execute(refreshToken: $this->loginRefreshToken);
            self::fail('An expired refresh token must be rejected.');
        } catch (InvalidRefreshToken $rejection) {
            self::assertSame('The refresh token has expired.', $rejection->getMessage());
        }

        $loginToken = $this->refreshTokens->findByHash(tokenHash: RefreshTokenHash::of(refreshToken: $this->loginRefreshToken));

        self::assertNull($loginToken?->revokedAt);
    }
}
