<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObject;

use Domain\Shared\Exception\InvalidIdentifier;
use Domain\Shared\ValueObject\Uuid7;

final readonly class UserId
{
    private function __construct(
        private Uuid7 $uuid,
    ) {}

    /**
     * @throws InvalidIdentifier
     */
    public static function fromString(string $id): self
    {
        return new self(uuid: Uuid7::fromString(candidate: $id));
    }

    public function toString(): string
    {
        return $this->uuid->value;
    }

    public function equals(self $other): bool
    {
        return $this->uuid->equals(other: $other->uuid);
    }
}
