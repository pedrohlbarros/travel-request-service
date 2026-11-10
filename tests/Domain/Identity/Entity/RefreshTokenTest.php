<?php

declare(strict_types=1);

namespace Tests\Domain\Identity\Entity;

use DateTimeImmutable;
use Domain\Identity\Entity\RefreshToken;
use Domain\Identity\Exception\InvalidRefreshToken;
use Domain\Identity\ValueObject\RefreshTokenFamilyId;
use Domain\Identity\ValueObject\RefreshTokenHash;
use Domain\Identity\ValueObject\RefreshTokenId;
use Domain\Identity\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Tests\Support\Identity\Fixture\IdentityMother as Mother;

final class RefreshTokenTest extends TestCase
{
    private const string TOKEN_ID = '019a6a20-aaaa-7bbb-8ccc-0000000000d1';

    private const string FAMILY_ID = '019a6a20-aaaa-7bbb-8ccc-0000000000e1';

    private const string EXPIRES_AT = '2026-11-16 10:00:00';

    public function test_IssuedTokenIsFreshAndKeepsEveryField(): void
    {
        $refreshToken = $this->issuedToken();

        self::assertSame(self::TOKEN_ID, $refreshToken->id->toString());
        self::assertSame(self::FAMILY_ID, $refreshToken->familyId->toString());
        self::assertSame(Mother::USER_ID, $refreshToken->userId->toString());
        self::assertSame(RefreshTokenHash::of(refreshToken: 'plain')->value, $refreshToken->tokenHash->value);
        self::assertEquals(Mother::moment(), $refreshToken->issuedAt);
        self::assertEquals(Mother::moment(moment: self::EXPIRES_AT), $refreshToken->expiresAt);
        self::assertFalse($refreshToken->wasRotated());
        self::assertNull($refreshToken->rotatedAt);
        self::assertNull($refreshToken->revokedAt);
    }

    public function test_IsUsableUntilJustBeforeExpiry(): void
    {
        $refreshToken = $this->issuedToken();

        $refreshToken->assertUsableAt(moment: Mother::moment(moment: '2026-11-16 09:59:59'));

        $this->addToAssertionCount(1);
    }

    public function test_IsNotUsableAtExpiry(): void
    {
        $refreshToken = $this->issuedToken();

        $this->expectException(InvalidRefreshToken::class);
        $this->expectExceptionMessageIs('The refresh token has expired.');

        $refreshToken->assertUsableAt(moment: Mother::moment(moment: self::EXPIRES_AT));
    }

    public function test_IsNotUsableOnceRevoked(): void
    {
        $revokedToken = $this->storedToken(rotatedAt: null, revokedAt: Mother::moment());

        $this->expectException(InvalidRefreshToken::class);
        $this->expectExceptionMessageIs('The refresh token was revoked.');

        $revokedToken->assertUsableAt(moment: Mother::moment());
    }

    public function test_KnowsWhenItWasRotated(): void
    {
        $rotatedToken = $this->storedToken(rotatedAt: Mother::moment(), revokedAt: null);

        self::assertTrue($rotatedToken->wasRotated());
        self::assertEquals(Mother::moment(), $rotatedToken->rotatedAt);
    }

    private function issuedToken(): RefreshToken
    {
        return RefreshToken::issue(
            id: RefreshTokenId::fromString(id: self::TOKEN_ID),
            familyId: RefreshTokenFamilyId::fromString(id: self::FAMILY_ID),
            userId: UserId::fromString(id: Mother::USER_ID),
            tokenHash: RefreshTokenHash::of(refreshToken: 'plain'),
            issuedAt: Mother::moment(),
            expiresAt: Mother::moment(moment: self::EXPIRES_AT),
        );
    }

    private function storedToken(?DateTimeImmutable $rotatedAt, ?DateTimeImmutable $revokedAt): RefreshToken
    {
        return RefreshToken::reconstitute(
            id: RefreshTokenId::fromString(id: self::TOKEN_ID),
            familyId: RefreshTokenFamilyId::fromString(id: self::FAMILY_ID),
            userId: UserId::fromString(id: Mother::USER_ID),
            tokenHash: RefreshTokenHash::of(refreshToken: 'plain'),
            issuedAt: Mother::moment(),
            expiresAt: Mother::moment(moment: self::EXPIRES_AT),
            rotatedAt: $rotatedAt,
            revokedAt: $revokedAt,
        );
    }
}
