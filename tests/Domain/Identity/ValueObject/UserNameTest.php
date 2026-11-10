<?php

declare(strict_types=1);

namespace Tests\Domain\Identity\ValueObject;

use Domain\Identity\Exception\InvalidUserName;
use Domain\Identity\ValueObject\UserName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserNameTest extends TestCase
{
    public function test_TrimsSurroundingWhitespace(): void
    {
        $name = UserName::fromString(name: '  Roronoa Zoro ');

        self::assertSame('Roronoa Zoro', $name->value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function acceptedNames(): array
    {
        return [
            'shortest' => [str_repeat('a', UserName::SHORTEST_NAME)],
            'longest' => [str_repeat('a', UserName::LONGEST_NAME)],
            'longestInMultibyteCharacters' => [str_repeat('ç', UserName::LONGEST_NAME)],
        ];
    }

    #[DataProvider('acceptedNames')]
    public function test_AcceptsNamesWithinLengthLimits(string $name): void
    {
        self::assertSame($name, UserName::fromString(name: $name)->value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedNames(): array
    {
        return [
            'onlyWhitespace' => ['   '],
            'tooShortAfterTrim' => ['  a '],
            'tooLong' => [str_repeat('a', UserName::LONGEST_NAME + 1)],
        ];
    }

    #[DataProvider('rejectedNames')]
    public function test_RejectsNamesOutsideLengthLimits(string $name): void
    {
        $this->expectException(InvalidUserName::class);
        $this->expectExceptionMessageIs('The name must have between 2 and 120 characters.');

        UserName::fromString(name: $name);
    }
}
