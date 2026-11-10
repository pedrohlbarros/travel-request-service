<?php

declare(strict_types=1);

namespace Tests\Domain\Identity\ValueObject;

use Domain\Identity\ValueObject\RefreshTokenFamilyId;
use Domain\Identity\ValueObject\RefreshTokenId;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Exception\InvalidIdentifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdentifiersTest extends TestCase
{
    private const string UUID7 = '019a6a20-aaaa-7bbb-8ccc-0000000000a1';

    private const string OTHER_UUID7 = '019a6a20-aaaa-7bbb-8ccc-0000000000a2';

    /**
     * @return array<string, array{class-string<UserId|RefreshTokenId|RefreshTokenFamilyId>}>
     */
    public static function identifierClasses(): array
    {
        return [
            'user' => [UserId::class],
            'refreshToken' => [RefreshTokenId::class],
            'refreshTokenFamily' => [RefreshTokenFamilyId::class],
        ];
    }

    /**
     * @param class-string<UserId|RefreshTokenId|RefreshTokenFamilyId> $identifierClass
     */
    #[DataProvider('identifierClasses')]
    public function test_KeepsCanonicalValueAndComparesByIt(string $identifierClass): void
    {
        $id = $identifierClass::fromString(strtoupper(self::UUID7));
        $sameId = $identifierClass::fromString(self::UUID7);
        $otherId = $identifierClass::fromString(self::OTHER_UUID7);

        self::assertSame(self::UUID7, $id->toString());
        self::assertTrue($id->equals(other: $sameId));
        self::assertFalse($id->equals(other: $otherId));
    }

    /**
     * @param class-string<UserId|RefreshTokenId|RefreshTokenFamilyId> $identifierClass
     */
    #[DataProvider('identifierClasses')]
    public function test_RejectsInvalidUuid(string $identifierClass): void
    {
        $this->expectException(InvalidIdentifier::class);

        $identifierClass::fromString('not-a-uuid');
    }
}
