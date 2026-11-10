<?php

declare(strict_types=1);

namespace Tests\Domain\TravelRequest\ValueObject;

use Domain\TravelRequest\Exception\InvalidDestination;
use Domain\TravelRequest\ValueObject\Destination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DestinationTest extends TestCase
{
    public function test_TrimsSurroundingWhitespace(): void
    {
        $destination = Destination::fromString("  São Paulo \t");

        self::assertSame('São Paulo', $destination->name);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function acceptedNames(): array
    {
        return [
            'shortest' => [str_repeat('a', Destination::SHORTEST_NAME)],
            'longest' => [str_repeat('a', Destination::LONGEST_NAME)],
            'longestInMultibyteCharacters' => [str_repeat('ã', Destination::LONGEST_NAME)],
        ];
    }

    #[DataProvider('acceptedNames')]
    public function test_AcceptsNamesWithinLengthLimits(string $name): void
    {
        $destination = Destination::fromString($name);

        self::assertSame($name, $destination->name);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedNames(): array
    {
        return [
            'empty' => [''],
            'onlyWhitespace' => ['    '],
            'tooShort' => [str_repeat('a', Destination::SHORTEST_NAME - 1)],
            'tooShortAfterTrim' => ['  a  '],
            'tooLong' => [str_repeat('a', Destination::LONGEST_NAME + 1)],
        ];
    }

    #[DataProvider('rejectedNames')]
    public function test_RejectsNamesOutsideLengthLimits(string $name): void
    {
        $this->expectException(InvalidDestination::class);
        $this->expectExceptionMessageIsOrContains('between 2 and 120 characters');

        Destination::fromString($name);
    }
}
