<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObject;

use Domain\Shared\Exception\InvalidIdentifier;

final readonly class Uuid7
{
    private const string CANONICAL_UUID_V7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidIdentifier
     */
    public static function fromString(string $candidate): self
    {
        $lowercased = strtolower($candidate);

        if (preg_match(self::CANONICAL_UUID_V7, $lowercased) !== 1) {
            throw InvalidIdentifier::notUuid7($candidate);
        }

        return new self($lowercased);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
