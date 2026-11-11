<?php

declare(strict_types=1);

namespace Domain\Notification\ValueObject;

use Domain\Notification\Exception\InvalidDecision;
use Domain\Shared\Exception\InvalidIdentifier;
use Domain\Shared\ValueObject\Uuid7;

/**
 * Who asked for the trip, as the decision event describes them; Notification never looks users up.
 */
final readonly class Recipient
{
    private function __construct(
        public Uuid7 $id,
        public string $name,
        public string $email,
    ) {}

    /**
     * @throws InvalidIdentifier
     * @throws InvalidDecision
     */
    public static function of(string $id, string $name, string $email): self
    {
        $trimmedName = trim($name);

        if ($trimmedName === '') {
            throw InvalidDecision::blank(field: 'requester name');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidDecision::invalidEmail(email: $email);
        }

        return new self(id: Uuid7::fromString(candidate: $id), name: $trimmedName, email: $email);
    }
}
