<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObject;

use Domain\Identity\Exception\InvalidEmail;

final readonly class Email
{
    public const int LONGEST_ADDRESS = 254;

    private function __construct(
        public string $address,
    ) {}

    /**
     * @throws InvalidEmail
     */
    public static function fromString(string $address): self
    {
        $normalizedAddress = strtolower(trim($address));
        $isWellFormed = filter_var($normalizedAddress, FILTER_VALIDATE_EMAIL) !== false;

        if (! $isWellFormed || \strlen($normalizedAddress) > self::LONGEST_ADDRESS) {
            throw InvalidEmail::malformed(address: $address);
        }

        return new self(address: $normalizedAddress);
    }

    public function equals(self $other): bool
    {
        return $this->address === $other->address;
    }
}
