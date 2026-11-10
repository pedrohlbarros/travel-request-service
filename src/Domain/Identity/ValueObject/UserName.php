<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObject;

use Domain\Identity\Exception\InvalidUserName;

final readonly class UserName
{
    public const int SHORTEST_NAME = 2;

    public const int LONGEST_NAME = 120;

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidUserName
     */
    public static function fromString(string $name): self
    {
        $trimmedName = trim($name);
        $nameLength = mb_strlen($trimmedName);

        if ($nameLength < self::SHORTEST_NAME || $nameLength > self::LONGEST_NAME) {
            throw InvalidUserName::lengthOutOfRange(shortestName: self::SHORTEST_NAME, longestName: self::LONGEST_NAME);
        }

        return new self(value: $trimmedName);
    }
}
