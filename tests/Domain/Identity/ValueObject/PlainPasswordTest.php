<?php

declare(strict_types=1);

namespace Tests\Domain\Identity\ValueObject;

use Domain\Identity\Exception\InvalidPassword;
use Domain\Identity\ValueObject\PlainPassword;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlainPasswordTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function acceptedPasswords(): array
    {
        return [
            'shortest' => [str_repeat('p', PlainPassword::SHORTEST_PASSWORD)],
            'longest' => [str_repeat('p', PlainPassword::LONGEST_PASSWORD)],
            'longestInMultibyteCharacters' => [str_repeat('ç', PlainPassword::LONGEST_PASSWORD)],
            'keepsSurroundingSpaces' => ['  secret  '],
        ];
    }

    #[DataProvider('acceptedPasswords')]
    public function test_AcceptsPasswordsWithinLengthLimits(string $password): void
    {
        self::assertSame($password, PlainPassword::fromString(password: $password)->value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedPasswords(): array
    {
        return [
            'tooShort' => [str_repeat('p', PlainPassword::SHORTEST_PASSWORD - 1)],
            'tooLong' => [str_repeat('p', PlainPassword::LONGEST_PASSWORD + 1)],
        ];
    }

    #[DataProvider('rejectedPasswords')]
    public function test_RejectsPasswordsOutsideLengthLimits(string $password): void
    {
        $this->expectException(InvalidPassword::class);
        $this->expectExceptionMessageIs('The password must have between 8 and 72 characters.');

        PlainPassword::fromString(password: $password);
    }
}
