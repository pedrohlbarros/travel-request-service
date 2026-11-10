<?php

declare(strict_types=1);

namespace Tests\Domain\Shared\ValueObject;

use Domain\Shared\Exception\InvalidIdentifier;
use Domain\Shared\ValueObject\Uuid7;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Uuid7Test extends TestCase
{
    private const string CANONICAL_UUID7 = '019a6a10-aaaa-7bbb-8ccc-0123456789ab';

    public function test_AcceptsCanonicalUuid7(): void
    {
        $uuid = Uuid7::fromString(candidate: self::CANONICAL_UUID7);

        self::assertSame(
            self::CANONICAL_UUID7,
            $uuid->value,
        );
    }

    public function test_NormalizesUppercaseToLowercase(): void
    {
        $uuid = Uuid7::fromString(candidate: strtoupper(self::CANONICAL_UUID7));

        self::assertSame(
            self::CANONICAL_UUID7,
            $uuid->value,
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notUuid7(): array
    {
        return [
            'empty' => [''],
            'version4' => ['019a6a10-aaaa-4bbb-8ccc-0123456789ab'],
            'nonRfcVariant' => ['019a6a10-aaaa-7bbb-cccc-0123456789ab'],
            'withoutHyphens' => ['019a6a10aaaa7bbb8ccc0123456789ab'],
            'withBraces' => ['{019a6a10-aaaa-7bbb-8ccc-0123456789ab}'],
            'trailingNewline' => [self::CANONICAL_UUID7 . "\n"],
            'leadingText' => ['id:' . self::CANONICAL_UUID7],
            'nonHexDigit' => ['019a6a10-aaaa-7bbb-8ccc-0123456789ag'],
            'oneDigitShort' => ['019a6a10-aaaa-7bbb-8ccc-0123456789a'],
        ];
    }

    #[DataProvider('notUuid7')]
    public function test_RejectsAnythingButCanonicalUuid7(string $candidate): void
    {
        $this->expectException(InvalidIdentifier::class);

        Uuid7::fromString(candidate: $candidate);
    }

    public function test_EqualsSameValueRegardlessOfCase(): void
    {
        $lowercase = Uuid7::fromString(candidate: self::CANONICAL_UUID7);
        $uppercase = Uuid7::fromString(candidate: strtoupper(self::CANONICAL_UUID7));
        $different = Uuid7::fromString(candidate: '019a6a10-aaaa-7bbb-8ccc-0123456789ac');

        self::assertTrue($lowercase->equals(other: $uppercase));
        self::assertFalse($lowercase->equals(other: $different));
    }
}
