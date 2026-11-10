<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObject;

use Domain\Identity\Exception\InvalidPassword;
use SensitiveParameter;

/**
 * A password chosen by the user, checked against the policy before it is hashed.
 * Login does not use it: existing passwords are verified as they are.
 */
final readonly class PlainPassword
{
    public const int SHORTEST_PASSWORD = 8;

    public const int LONGEST_PASSWORD = 72;

    private function __construct(
        #[SensitiveParameter]
        public string $value,
    ) {}

    /**
     * @throws InvalidPassword
     */
    public static function fromString(#[SensitiveParameter] string $password): self
    {
        $passwordLength = mb_strlen($password);

        if ($passwordLength < self::SHORTEST_PASSWORD || $passwordLength > self::LONGEST_PASSWORD) {
            throw InvalidPassword::lengthOutOfRange(
                shortestPassword: self::SHORTEST_PASSWORD,
                longestPassword: self::LONGEST_PASSWORD,
            );
        }

        return new self(value: $password);
    }
}
