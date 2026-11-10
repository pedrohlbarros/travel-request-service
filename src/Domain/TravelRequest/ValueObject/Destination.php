<?php

declare(strict_types=1);

namespace Domain\TravelRequest\ValueObject;

use Domain\TravelRequest\Exception\InvalidDestination;

final readonly class Destination
{
    public const int SHORTEST_NAME = 2;

    public const int LONGEST_NAME = 120;

    private function __construct(
        public string $name,
    ) {}

    /**
     * @throws InvalidDestination
     */
    public static function fromString(string $name): self
    {
        $trimmedName = trim($name);
        $nameLength = mb_strlen($trimmedName);

        if ($nameLength < self::SHORTEST_NAME || $nameLength > self::LONGEST_NAME) {
            throw InvalidDestination::lengthOutOfRange($trimmedName, self::SHORTEST_NAME, self::LONGEST_NAME);
        }

        return new self($trimmedName);
    }
}
