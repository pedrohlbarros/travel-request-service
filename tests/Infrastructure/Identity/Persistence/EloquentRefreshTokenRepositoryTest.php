<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Identity\Persistence;

use DateTimeImmutable;
use Domain\Identity\Entity\RefreshToken;
use Domain\Identity\ValueObject\RefreshTokenFamilyId;
use Domain\Identity\ValueObject\RefreshTokenHash;
use Domain\Identity\ValueObject\RefreshTokenId;
use Domain\Identity\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Identity\Persistence\EloquentRefreshTokenRepository;
use Infrastructure\Identity\Persistence\EloquentUserRepository;
use Tests\Support\Identity\Fixture\IdentityMother as Mother;
use Tests\Support\LaravelTestCase;

final class EloquentRefreshTokenRepositoryTest extends LaravelTestCase
{
    use RefreshDatabase;

    private const string FAMILY_ID = '019a6a20-aaaa-7bbb-8ccc-0000000000e1';

    private const string OTHER_FAMILY_ID = '019a6a20-aaaa-7bbb-8ccc-0000000000e2';

    private const string FIRST_TOKEN_ID = '019a6a20-aaaa-7bbb-8ccc-0000000000d1';

    private const string SECOND_TOKEN_ID = '019a6a20-aaaa-7bbb-8ccc-0000000000d2';

    private const string THIRD_TOKEN_ID = '019a6a20-aaaa-7bbb-8ccc-0000000000d3';

    private const string EXPIRES_AT = '2026-11-16 10:00:00';

    private const string LATER = '2026-11-10 09:00:00';

    private EloquentRefreshTokenRepository $refreshTokens;

    protected function setUp(): void
    {
        parent::setUp();
        new EloquentUserRepository()->add(user: Mother::user());
        $this->refreshTokens = new EloquentRefreshTokenRepository();
    }

    public function test_StoresAndFindsByHash(): void
    {
        $refreshToken = $this->token(id: self::FIRST_TOKEN_ID, familyId: self::FAMILY_ID, plainToken: 'plain-1');

        $this->refreshTokens->add(refreshToken: $refreshToken);

        $found = $this->stored(plainToken: 'plain-1');
        $neverIssued = $this->stored(plainToken: 'never-issued');

        self::assertEquals($refreshToken, $found);
        self::assertNull($neverIssued);
    }

    public function test_OnlyTheFirstRotationWins(): void
    {
        $refreshToken = $this->token(id: self::FIRST_TOKEN_ID, familyId: self::FAMILY_ID, plainToken: 'plain-1');
        $this->refreshTokens->add(refreshToken: $refreshToken);
        $rotatedAt = Mother::moment(moment: self::LATER);

        $isFirstRotationWon = $this->refreshTokens->markRotated(refreshToken: $refreshToken, rotatedAt: $rotatedAt);
        $isSecondRotationWon = $this->refreshTokens->markRotated(refreshToken: $refreshToken, rotatedAt: $rotatedAt);

        $rotatedToken = $this->stored(plainToken: 'plain-1');

        self::assertTrue($isFirstRotationWon);
        self::assertFalse($isSecondRotationWon);
        self::assertEquals($rotatedAt, $rotatedToken?->rotatedAt);
    }

    public function test_RevokedTokenCannotBeRotated(): void
    {
        $refreshToken = $this->token(id: self::FIRST_TOKEN_ID, familyId: self::FAMILY_ID, plainToken: 'plain-1');
        $this->refreshTokens->add(refreshToken: $refreshToken);
        $this->revokeFamily(familyId: self::FAMILY_ID, revokedAt: Mother::moment(moment: self::LATER));

        $isRotationWon = $this->refreshTokens->markRotated(
            refreshToken: $refreshToken,
            rotatedAt: Mother::moment(moment: self::LATER),
        );

        self::assertFalse($isRotationWon);
    }

    public function test_RevokingAFamilyLeavesOtherFamiliesAlone(): void
    {
        $this->addToken(id: self::FIRST_TOKEN_ID, familyId: self::FAMILY_ID, plainToken: 'plain-1');
        $this->addToken(id: self::SECOND_TOKEN_ID, familyId: self::FAMILY_ID, plainToken: 'plain-2');
        $this->addToken(id: self::THIRD_TOKEN_ID, familyId: self::OTHER_FAMILY_ID, plainToken: 'plain-3');
        $revokedAt = Mother::moment(moment: self::LATER);

        $this->revokeFamily(familyId: self::FAMILY_ID, revokedAt: $revokedAt);

        $firstOfFamily = $this->stored(plainToken: 'plain-1');
        $secondOfFamily = $this->stored(plainToken: 'plain-2');
        $ofOtherFamily = $this->stored(plainToken: 'plain-3');

        self::assertEquals($revokedAt, $firstOfFamily?->revokedAt);
        self::assertEquals($revokedAt, $secondOfFamily?->revokedAt);
        self::assertNull($ofOtherFamily?->revokedAt);
    }

    public function test_RevokingAgainKeepsTheFirstRevocationTime(): void
    {
        $this->addToken(id: self::FIRST_TOKEN_ID, familyId: self::FAMILY_ID, plainToken: 'plain-1');
        $firstRevocation = Mother::moment(moment: self::LATER);

        $this->revokeFamily(familyId: self::FAMILY_ID, revokedAt: $firstRevocation);
        $this->revokeFamily(familyId: self::FAMILY_ID, revokedAt: Mother::moment(moment: self::EXPIRES_AT));

        $revokedToken = $this->stored(plainToken: 'plain-1');

        self::assertEquals($firstRevocation, $revokedToken?->revokedAt);
    }

    private function token(string $id, string $familyId, string $plainToken): RefreshToken
    {
        return RefreshToken::issue(
            id: RefreshTokenId::fromString(id: $id),
            familyId: RefreshTokenFamilyId::fromString(id: $familyId),
            userId: UserId::fromString(id: Mother::USER_ID),
            tokenHash: RefreshTokenHash::of(refreshToken: $plainToken),
            issuedAt: Mother::moment(),
            expiresAt: Mother::moment(moment: self::EXPIRES_AT),
        );
    }

    private function addToken(string $id, string $familyId, string $plainToken): void
    {
        $refreshToken = $this->token(id: $id, familyId: $familyId, plainToken: $plainToken);

        $this->refreshTokens->add(refreshToken: $refreshToken);
    }

    private function revokeFamily(string $familyId, DateTimeImmutable $revokedAt): void
    {
        $this->refreshTokens->revokeFamily(
            familyId: RefreshTokenFamilyId::fromString(id: $familyId),
            revokedAt: $revokedAt,
        );
    }

    private function stored(string $plainToken): ?RefreshToken
    {
        $tokenHash = RefreshTokenHash::of(refreshToken: $plainToken);

        return $this->refreshTokens->findByHash(tokenHash: $tokenHash);
    }
}
