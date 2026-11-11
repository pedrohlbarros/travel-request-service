<?php

declare(strict_types=1);

namespace Domain\Notification\Exception;

use InvalidArgumentException;

final class InvalidDecision extends InvalidArgumentException
{
    public static function blank(string $field): self
    {
        return new self(\sprintf('The decision has no %s.', $field));
    }

    public static function invalidEmail(string $email): self
    {
        return new self(\sprintf('"%s" is not an e-mail address.', $email));
    }

    public static function unknownOutcome(string $outcome): self
    {
        return new self(\sprintf('"%s" is not a decision outcome.', $outcome));
    }

    public static function returnBeforeDeparture(): self
    {
        return new self('The decision returns before it departs.');
    }
}
