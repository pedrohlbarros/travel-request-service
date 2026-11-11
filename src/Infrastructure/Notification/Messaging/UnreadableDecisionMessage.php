<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Messaging;

use RuntimeException;

final class UnreadableDecisionMessage extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self(\sprintf('The decision message cannot be read: %s.', $reason));
    }
}
